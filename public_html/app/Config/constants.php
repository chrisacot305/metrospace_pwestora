<?php
/**
 * app/Config/constants.php
 * Turns .env settings into the constants the whole system has always used
 * (DB_HOST, APP_URL, SMTP_HOST, PHILSMS_API_TOKEN, ...), so existing code keeps working.
 * NO passwords are written in this file — they only live in .env.
 */
use App\Core\Env;

$constants = [
    // Database
    'DB_HOST' => Env::get('DB_HOST', 'localhost'),
    'DB_NAME' => Env::get('DB_NAME', 'u456796838_pwestora'),
    'DB_USER' => Env::get('DB_USER', 'u456796838_pwestora'),
    'DB_PASS' => (string) Env::get('DB_PASS', 'pwestora*BSIT4B'),

    // Application
    'APP_NAME'  => Env::get('APP_NAME', 'Pwestora'),
    'APP_URL'   => rtrim((string) Env::get('APP_URL', 'https://pwestora.com'), '/'),
    'APP_ENV'   => Env::get('APP_ENV', 'production'),
    'APP_DEBUG' => Env::get('APP_DEBUG', false) === true,
    'UPLOAD_DIR' => APP_ROOT . '/uploads',

    // Email (SMTP)
    'SMTP_HOST'       => Env::get('SMTP_HOST', 'smtp.hostinger.com'),
    'SMTP_PORT'       => (int) Env::get('SMTP_PORT', 465),
    'SMTP_ENCRYPTION' => Env::get('SMTP_ENCRYPTION', 'ssl'),
    'SMTP_USER'       => Env::get('SMTP_USER', ''),
    'SMTP_PASS'       => (string) Env::get('SMTP_PASS', ''),
    'SMTP_FROM_NAME'  => Env::get('SMTP_FROM_NAME', 'Pwestora'),
    'MAIL_DRIVER'     => Env::get('MAIL_DRIVER', 'smtp'),     // smtp | log

    // SMS (PhilSMS)
    'PHILSMS_API_TOKEN' => (string) Env::get('PHILSMS_API_TOKEN', ''),
    'PHILSMS_SENDER_ID' => Env::get('PHILSMS_SENDER_ID', 'PhilSMS'),
    'PHILSMS_API_URL'   => Env::get('PHILSMS_API_URL', 'https://dashboard.philsms.com/api/v3/sms/send'),
    'SMS_DRIVER'        => Env::get('SMS_DRIVER', 'philsms'),  // philsms | log

    // Security
    'CRON_SECRET_KEY'  => (string) Env::get('CRON_SECRET_KEY', ''),
    'SESSION_LIFETIME' => (int) Env::get('SESSION_LIFETIME', 7200), // seconds of inactivity before logout

    // Mobile API
    'API_DEMO_MODE'      => Env::get('API_DEMO_MODE', false) === true,
    'API_ALLOWED_ORIGIN' => Env::get('API_ALLOWED_ORIGIN', '*'),
];

foreach ($constants as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

// Optional one-time setup key for tools/migrate.php over the web.
$migrateKey = (string) Env::get('MIGRATE_KEY', '');
if ($migrateKey !== '' && !defined('MIGRATE_KEY')) {
    define('MIGRATE_KEY', $migrateKey);
}
unset($constants, $name, $value, $migrateKey);
