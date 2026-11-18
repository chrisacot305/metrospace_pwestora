<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Services\RateLimiter;

require_once APP_PATH . '/Services/Mailer.php';

/**
 * PasswordResetController — "Forgot password?" for the website.
 *   /forgot_password.php → request()  : enter email, receive a one-time link
 *   /reset_password.php  → reset()    : open the link, choose a new password
 *
 * How it stays secure:
 *  - Same answer whether or not the email exists (no account enumeration).
 *  - Email is sent AFTER the page is returned when the server allows it, so response time
 *    doesn't reveal whether the account exists either.
 *  - Rate limits: 3 links per email and 10 per IP per hour; 30 link checks per IP per 15 min.
 *  - Links: 256-bit random, stored hashed, valid 30 minutes, single use, newest link only.
 *  - The token is moved out of the URL into the session immediately (not kept in history,
 *    not leaked through the Referer header).
 *  - The link is built from APP_URL, never from the request's Host header.
 *  - After a reset: other web sessions and the mobile app are logged out, the login lockout
 *    is cleared, the event is audit-logged, and the owner gets a "password changed" email.
 */
final class PasswordResetController extends Controller
{
    private const SESSION_KEY = 'pw_reset_token';

    public function request(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard.php');
        }
        $this->noCacheHeaders();

        $error = '';
        $sent = false;
        $email = '';
        $mailJob = null;

        if ($this->isPost()) {
            Csrf::check();
            $email = mb_substr(strtolower(Validator::str($_POST, 'email', 150)), 0, 150);

            if (!Validator::email($email)) {
                $error = 'Please enter a valid email address.';
            } else {
                $limiter  = new RateLimiter($this->pdo);
                $ip       = $this->clientIp();
                $emailKey = 'pw-reset:email:' . $email;
                $ipKey    = 'pw-reset:ip:' . $ip;

                if ($limiter->tooManyAttempts($ipKey, 10, 3600)) {
                    $error = 'Too many reset requests from your network. Please wait an hour and try again.';
                } else {
                    $limiter->hit($ipKey, 3600);
                    $sent = true; // always show the same message from here on

                    // Per-email limit is silent, so it can't be used to test which emails exist.
                    if (!$limiter->tooManyAttempts($emailKey, 3, 3600)) {
                        $limiter->hit($emailKey, 3600);
                        $user = (new UserModel($this->pdo))->findByEmail($email);

                        if ($user && ($user['status'] ?? '') !== 'suspended') {
                            try {
                                $token = (new PasswordResetModel($this->pdo))->create((int) $user['id'], $ip);
                                $mailJob = [$user, $token];
                                audit_log($this->pdo, (int) $user['id'], 'password reset requested', 'IP ' . $ip);
                            } catch (\Throwable $e) {
                                error_log('[PasswordReset create] ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }

        View::render('auth/forgot_password', ['error' => $error, 'sent' => $sent, 'email' => $email]);

        if ($mailJob) {
            $this->finishResponse();
            [$user, $token] = $mailJob;
            $this->sendResetEmail($user, $token);
        }
    }

    public function reset(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard.php');
        }
        $this->noCacheHeaders();

        $limiter = new RateLimiter($this->pdo);
        $ipKey   = 'pw-reset-check:ip:' . $this->clientIp();
        $resets  = new PasswordResetModel($this->pdo);

        // Step 1: arriving from the email link — move the token into the session and
        // reload without it, so it never sits in the address bar, history, or Referer.
        if (!$this->isPost() && isset($_GET['token'])) {
            $token = is_string($_GET['token']) ? strtolower(trim($_GET['token'])) : '';

            if ($limiter->tooManyAttempts($ipKey, 30, 900)) {
                $this->renderInvalid('Too many attempts. Please wait 15 minutes and try again.');
                return;
            }
            if (!$resets->findValid($token)) {
                $limiter->hit($ipKey, 900);
                unset($_SESSION[self::SESSION_KEY]);
                $this->renderInvalid();
                return;
            }
            Session::regenerate();
            $_SESSION[self::SESSION_KEY] = $token;
            $this->redirect('/reset_password.php');
        }

        // Step 2: show the form / save the new password.
        $token = is_string($_SESSION[self::SESSION_KEY] ?? null) ? $_SESSION[self::SESSION_KEY] : '';
        $row = $token !== '' ? $resets->findValid($token) : null;
        if (!$row || ($row['status'] ?? '') === 'suspended') {
            unset($_SESSION[self::SESSION_KEY]);
            $this->renderInvalid();
            return;
        }

        $error = '';
        if ($this->isPost()) {
            Csrf::check();
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

            $error = $this->passwordProblem($password, $confirm, (string) $row['email'], (string) $row['password_hash']);

            if ($error === '') {
                $ok = false;
                try {
                    $ok = $resets->consumeAndSetPassword($token, (int) $row['user_id'], password_hash($password, PASSWORD_DEFAULT));
                } catch (\Throwable $e) {
                    error_log('[PasswordReset save] ' . $e->getMessage());
                    $error = 'Something went wrong. Please try again.';
                }

                if ($ok) {
                    unset($_SESSION[self::SESSION_KEY]);
                    $limiter->clear('web-login:email:' . strtolower((string) $row['email']));
                    audit_log($this->pdo, (int) $row['user_id'], 'password reset completed', 'IP ' . $this->clientIp());

                    Session::regenerate();
                    $_SESSION['flash_success'] = 'Your password has been changed. You can now log in with your new password.';

                    // Tell the owner, so a reset they didn't make doesn't go unnoticed.
                    $this->finishResponseWithRedirect('/login.php');
                    send_email(
                        (string) $row['email'],
                        (string) $row['full_name'],
                        'Your Pwestora password was changed',
                        "Hi {$row['full_name']},\n\n"
                        . 'The password for your Pwestora account was just changed (' . date('M j, Y g:i A') . ").\n\n"
                        . "If you did this, no action is needed.\n\n"
                        . "If you did NOT change your password, reset it right away at:\n"
                        . APP_URL . "/forgot_password.php\n"
                        . "and contact Pwestora support.\n\n— Pwestora"
                    );
                    exit;
                }
                if ($error === '') {
                    unset($_SESSION[self::SESSION_KEY]);
                    $this->renderInvalid();
                    return;
                }
            }
        }

        View::render('auth/reset_password', [
            'error'       => $error,
            'email'       => (string) $row['email'],
            'minutesLeft' => PasswordResetModel::TOKEN_MINUTES,
        ]);
    }

    /** Returns '' when the password is acceptable, otherwise a message for the user. */
    private function passwordProblem(string $password, string $confirm, string $email, string $currentHash): string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if (strlen($password) > 72) {
            return 'Password must be 72 characters or fewer.'; // bcrypt ignores anything after 72 bytes
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Use at least one letter and one number.';
        }
        if (!hash_equals($password, $confirm)) {
            return 'The two passwords do not match.';
        }
        $lower = strtolower($password);
        $local = strtolower(strstr($email, '@', true) ?: $email);
        if ($lower === strtolower($email) || (strlen($local) >= 4 && str_contains($lower, $local))) {
            return 'Your password should not contain your email address.';
        }
        $common = ['password1', 'password123', '12345678a', 'qwerty123', 'abc12345', 'pwestora1',
                   'pwestora123', 'admin123', 'welcome1', 'iloveyou1', 'passw0rd', 'letmein1'];
        if (in_array($lower, $common, true)) {
            return 'That password is too common. Please choose another.';
        }
        if ($currentHash !== '' && password_verify($password, $currentHash)) {
            return 'Please choose a password different from your current one.';
        }
        return '';
    }

    private function sendResetEmail(array $user, string $token): void
    {
        $link = APP_URL . '/reset_password.php?token=' . $token;
        $mins = PasswordResetModel::TOKEN_MINUTES;
        send_email(
            (string) $user['email'],
            (string) $user['full_name'],
            'Reset your Pwestora password',
            "Hi {$user['full_name']},\n\n"
            . "We received a request to reset the password for your Pwestora account.\n\n"
            . "Open this link to choose a new password:\n$link\n\n"
            . "The link works once and expires in $mins minutes.\n\n"
            . "If you didn't ask for this, you can ignore this email — your password stays the same.\n"
            . "Never share this link with anyone. Pwestora staff will never ask for it.\n\n— Pwestora"
        );
    }

    private function renderInvalid(string $message = ''): void
    {
        View::render('auth/reset_password', [
            'invalid' => true,
            'error'   => $message,
        ]);
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private function noCacheHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('Pragma: no-cache');
            header('Referrer-Policy: no-referrer');
        }
    }

    /** Sends the page to the browser now, so slow work after this (SMTP) doesn't change response time. */
    private function finishResponse(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(true);
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_end_flush();
            @flush();
        }
    }

    private function finishResponseWithRedirect(string $url): void
    {
        header('Location: ' . $url);
        http_response_code(303);
        $this->finishResponse();
    }
}
