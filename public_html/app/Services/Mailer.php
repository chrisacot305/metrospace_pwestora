<?php
/**
 * app/Services/Mailer.php  (was includes/mailer.php)
 * Sends real email via authenticated SMTP (your admin@pwestora.com mailbox),
 * using PHPMailer — vendored in app/Libraries/PHPMailer/, no Composer
 * needed. The mailbox password is SMTP_PASS in .env.
 */
require_once __DIR__ . '/../Libraries/PHPMailer/Exception.php';
require_once __DIR__ . '/../Libraries/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../Libraries/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Sends a plain-text email. Returns true on success, false on failure
 * (failures are logged, never shown to the visitor).
 */
if (!function_exists('send_email')) {
function send_email(string $toEmail, string $toName, string $subject, string $body): bool {
    // MAIL_DRIVER=log (testing): write the email to app/storage/logs/outbox.log instead of sending it.
    if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'log') {
        @file_put_contents(APP_PATH . '/storage/logs/outbox.log', date('c') . " MAIL to $toEmail: $subject\n$body\n\n", FILE_APPEND);
        return true;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Email send failed to ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}
}

if (!function_exists('send_otp_email')) {
function send_otp_email(string $toEmail, string $toName, string $code): bool {
    return send_email(
        $toEmail,
        $toName,
        'Your Pwestora login code',
        "Hi $toName,\n\nYour one-time login code is: $code\n\n"
        . "This code expires in 10 minutes. If you didn't try to log in, you can ignore this email.\n\n— Pwestora"
    );
}
}

/** Notifies a lessor their account verification was approved or rejected. */
if (!function_exists('send_lessor_status_email')) {
function send_lessor_status_email(string $toEmail, string $toName, bool $approved): bool {
    if ($approved) {
        $subject = 'Your Pwestora lessor account has been approved';
        $body = "Hi $toName,\n\nGood news — your business documents have been verified and your Pwestora "
              . "lessor account is now active. You can log in and start listing your commercial properties.\n\n"
              . APP_URL . "/login.php\n\n— Pwestora";
    } else {
        $subject = 'Your Pwestora lessor verification needs attention';
        $body = "Hi $toName,\n\nWe weren't able to approve your lessor account with the documents provided. "
              . "Please contact our support team or resubmit your registration with clearer documents.\n\n— Pwestora";
    }
    return send_email($toEmail, $toName, $subject, $body);
}
}

/** Notifies a lessor their property listing was verified or rejected. */
if (!function_exists('send_property_status_email')) {
function send_property_status_email(string $toEmail, string $toName, string $propertyName, bool $approved): bool {
    if ($approved) {
        $subject = "\"$propertyName\" has been verified";
        $body = "Hi $toName,\n\nYour property listing \"$propertyName\" has passed document review and is now "
              . "live and visible to tenants on Pwestora.\n\n— Pwestora";
    } else {
        $subject = "\"$propertyName\" needs attention";
        $body = "Hi $toName,\n\nWe weren't able to verify your property listing \"$propertyName\" with the "
              . "documents provided. Please review and resubmit with clearer documents.\n\n— Pwestora";
    }
    return send_email($toEmail, $toName, $subject, $body);
}
}
