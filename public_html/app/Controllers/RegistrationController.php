<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Upload;
use App\Core\Validator;
use App\Core\View;
use App\Models\LessorProfileModel;
use App\Models\UserModel;
use App\Services\RateLimiter;
use RuntimeException;

require_once APP_PATH . '/Services/Mailer.php';

/**
 * RegistrationController — lessor sign-up with documents + email OTP.
 *   /register.php   → register()
 *   /verify_otp.php → verifyOtp()
 */
final class RegistrationController extends Controller
{
    /** key => [db column, label] — the 5 documents SuperAdmin reviews in Verify Lessors. */
    private const REQUIRED_DOCS = [
        'business_permit' => ['business_permit_path', 'Business Permit'],
        'sec_dti'         => ['sec_dti_path',         'SEC / DTI Registration'],
        'bir_cert'        => ['bir_cert_path',        'BIR Certificate'],
        'land_title'      => ['land_title_path',      'Land Title'],
        'gov_id'          => ['gov_id_path',          "Owner's Government ID"],
    ];
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    private const MAX_FILE_MB = 5;
    private const OTP_MINUTES = 10;

    public function register(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard.php');
        }

        $error = '';
        $REQUIRED_DOCS = self::REQUIRED_DOCS;
        $ALLOWED_EXT = self::ALLOWED_EXT;
        $MAX_FILE_MB = self::MAX_FILE_MB;

        if ($this->isPost()) {
            Csrf::check();
            $company  = Validator::str($_POST, 'company_name', 150);
            $name     = Validator::str($_POST, 'full_name', 150);
            $email    = Validator::str($_POST, 'email', 150);
            $phone    = Validator::str($_POST, 'phone', 30);
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

            $users = new UserModel($this->pdo);

            if ($company === '' || $name === '' || $email === '' || strlen($password) < 8) {
                $error = 'Please fill in every field. Password must be at least 8 characters.';
            } elseif (!Validator::email($email)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($password) > 72) {
                $error = 'Password must be 72 characters or fewer.';
            } elseif ($users->emailExists($email)) {
                $error = 'An account with that email already exists.';
            } else {
                $users->beginTransaction();
                try {
                    $userId = $users->createLessor($company, $name, $email, $phone, password_hash($password, PASSWORD_DEFAULT));

                    $docPaths = [];
                    foreach (self::REQUIRED_DOCS as $field => [$column, $label]) {
                        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
                            throw new RuntimeException("Please attach a file for every document — \"$field\" is missing.");
                        }
                        $docPaths[$column] = Upload::save(
                            $_FILES[$field], "verification/{$userId}", $field, self::ALLOWED_EXT, self::MAX_FILE_MB,
                            'Only PDF, JPG, and PNG files are accepted.',
                            'Each file must be under ' . self::MAX_FILE_MB . 'MB.'
                        );
                    }
                    (new LessorProfileModel($this->pdo))->create($userId, $docPaths);

                    audit_log($this->pdo, $userId, 'register', "New lessor: $company");
                    $users->commit();

                    // Confirm the email address is real before letting them in.
                    $code = $this->newCode();
                    $users->setOtp($userId, $code, date('Y-m-d H:i:s', strtotime('+' . self::OTP_MINUTES . ' minutes')));
                    send_otp_email($email, $name, $code);

                    $_SESSION['otp_pending_user_id'] = $userId;
                    $this->redirect('/verify_otp.php');
                } catch (\Exception $e) {
                    $users->rollBack();
                    Upload::discardSaved();
                    // A thrown validation message (e.g. wrong file type) is safe to show as-is.
                    $error = ($e instanceof RuntimeException) ? $e->getMessage() : 'Something went wrong. Please try again.';
                    if (!($e instanceof RuntimeException)) {
                        error_log('Registration failed: ' . $e->getMessage());
                    }
                }
            }
        }

        View::render('auth/register', get_defined_vars());
    }

    public function verifyOtp(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard.php');
        }

        $pendingUserId = $_SESSION['otp_pending_user_id'] ?? null;
        if (!$pendingUserId) {
            $this->redirect('/login.php');
        }

        $users = new UserModel($this->pdo);
        $user = $users->findById((int) $pendingUserId);
        if (!$user) {
            unset($_SESSION['otp_pending_user_id']);
            $this->redirect('/login.php');
        }

        $error = '';
        $resent = false;
        $limiter = new RateLimiter($this->pdo);
        $guessKey  = 'email-otp-guess:' . $user['id'];
        $resendKey = 'email-otp-resend:' . $user['id'];

        if ($this->isPost() && isset($_POST['resend'])) {
            Csrf::check();
            if ($limiter->tooManyAttempts($resendKey, 5, 3600)) {
                $error = 'Too many codes requested. Please wait an hour and try again.';
            } else {
                $limiter->hit($resendKey, 3600);
                $limiter->clear($guessKey);
                $code = $this->newCode();
                $users->setOtp((int) $user['id'], $code, date('Y-m-d H:i:s', strtotime('+' . self::OTP_MINUTES . ' minutes')));
                send_otp_email($user['email'], $user['full_name'], $code);
                $resent = true;
            }
        } elseif ($this->isPost()) {
            Csrf::check();
            $submitted = Validator::str($_POST, 'code', 6);

            // Max 5 wrong guesses per code, then the code is destroyed.
            if ($limiter->tooManyAttempts($guessKey, 5, self::OTP_MINUTES * 60)) {
                $users->setOtp((int) $user['id'], null, null);
                $user['otp_code'] = null;
            }

            $valid = $user['otp_code'] !== null
                && hash_equals((string) $user['otp_code'], $submitted)
                && $user['otp_expires_at'] !== null
                && strtotime($user['otp_expires_at']) >= time();

            if (!$valid) {
                $limiter->hit($guessKey, self::OTP_MINUTES * 60);
                $error = 'That code is incorrect or has expired. Please try again or request a new one.';
            } else {
                $limiter->clear($guessKey);
                $users->setOtp((int) $user['id'], null, null);
                unset($_SESSION['otp_pending_user_id']);

                Auth::login($user);
                audit_log($this->pdo, (int) $user['id'], 'login (OTP verified)');
                $this->redirect($user['status'] === 'active' ? '/dashboard.php' : '/pending.php');
            }
        }

        View::render('auth/verify_otp', ['user' => $user, 'error' => $error, 'resent' => $resent]);
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
