<?php
/**
 * app/Services/Sms.php — Centralized SMS service (was includes/sms.php) for Pwestora via PhilSMS Gateway
 * ---------------------------------------------------------------------------
 * Provides robust formatting for Philippine numbers and direct REST API dispatch.
 * Easily swappable in the future without changing caller code.
 */

if (!defined('APP_PATH')) {
    require_once __DIR__ . '/../init.php';
}

/**
 * Normalizes any Philippine phone number to international standard without '+' (639XXXXXXXXX).
 * Accepts:
 *   - 09171234567
 *   - +639171234567
 *   - 639171234567
 *   - 9171234567
 * Returns null if the number is not a valid Philippine mobile number.
 */
if (!function_exists('format_ph_phone')) {
function format_ph_phone(?string $raw): ?string {
    if (!$raw) return null;
    // Strip non-digit characters
    $digits = preg_replace('/\D+/', '', $raw);

    // Standardize to 639XXXXXXXXX (12 digits)
    if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
        return '63' . substr($digits, 1);
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '63' . $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '639')) {
        return $digits;
    }

    return null;
}
}

/**
 * Sends an SMS text message using the PhilSMS v3 REST API.
 *
 * @param string $phone       Philippine mobile number (e.g. 0917xxxxxxx)
 * @param string $message     The SMS body content
 * @param string|null $sender Optional sender ID (defaults to PHILSMS_SENDER_ID)
 * @return array{ok: bool, message: string, data?: mixed, error?: string}
 */
if (!function_exists('send_sms')) {
function send_sms(string $phone, string $message, ?string $sender = null): array {
    // SMS_DRIVER=log (testing): write the SMS to app/storage/logs/outbox.log instead of sending it.
    if (defined('SMS_DRIVER') && SMS_DRIVER === 'log') {
        @file_put_contents(APP_PATH . '/storage/logs/outbox.log', date('c') . " SMS to $phone: $message\n", FILE_APPEND);
        return ['ok' => true, 'message' => 'SMS logged (SMS_DRIVER=log)'];
    }

    $normalizedPhone = format_ph_phone($phone);
    if (!$normalizedPhone) {
        return [
            'ok'    => false,
            'error' => 'Invalid Philippine mobile number. Must be an 11-digit number starting with 09 (e.g. 09171234567).'
        ];
    }

    $token = defined('PHILSMS_API_TOKEN') ? PHILSMS_API_TOKEN : '';
    if (empty($token)) {
        error_log('[SMS] PhilSMS API token is missing (set PHILSMS_API_TOKEN in .env)');
        return [
            'ok'    => false,
            'error' => 'SMS Gateway is not configured.'
        ];
    }

    $apiUrl = defined('PHILSMS_API_URL') ? PHILSMS_API_URL : 'https://dashboard.philsms.com/api/v3/sms/send';
    $senderId = $sender ?: (defined('PHILSMS_SENDER_ID') ? PHILSMS_SENDER_ID : 'PhilSMS');

    $payload = [
        'recipient' => $normalizedPhone,
        'sender_id' => $senderId,
        'type'      => 'plain',
        'message'   => $message,
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[SMS Error] cURL failure: $curlError");
        return [
            'ok'    => false,
            'error' => 'Network error connecting to SMS Gateway. Please try again later.'
        ];
    }

    $decoded = json_decode($rawResponse, true);

    // PhilSMS sometimes returns HTTP 200 with {"status":"error", "message":"..."}
    if (is_array($decoded) && isset($decoded['status']) && strtolower((string)$decoded['status']) === 'error') {
        $errorMsg = $decoded['message'] ?? 'PhilSMS rejected the request.';
        error_log("[SMS Error] PhilSMS returned status error: $rawResponse");
        return [
            'ok'    => false,
            'error' => 'SMS Gateway Error: ' . $errorMsg,
            'data'  => $decoded,
        ];
    }

    // PhilSMS returns HTTP 200 or 201 on successful queuing
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'ok'      => true,
            'message' => 'SMS queued successfully',
            'data'    => $decoded,
        ];
    }

    $errorMsg = $decoded['message'] ?? ($decoded['error'] ?? "HTTP $httpCode response from gateway");
    error_log("[SMS Error] PhilSMS returned HTTP $httpCode: $rawResponse");
    return [
        'ok'      => false,
        'error'   => $errorMsg,
        'data'    => $decoded,
    ];
}
}
