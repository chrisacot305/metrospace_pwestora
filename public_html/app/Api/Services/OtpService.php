<?php
namespace Api\Services;

use Api\Http\Request;
use Api\Repositories\PhoneVerificationRepository;

/**
 * SMS one-time codes for registration.
 *  - 6-digit code from a cryptographically secure random generator
 *  - valid for 10 minutes
 *  - one SMS per 60 seconds per number, max 5 per hour per number, 10 per hour per IP
 *  - max 5 wrong guesses, then the code is destroyed and a new one must be requested
 */
final class OtpService
{
    public const VALID_MINUTES      = 10;
    private const RESEND_SECONDS    = 60;
    private const MAX_SENDS_PER_HOUR = 5;
    private const MAX_IP_SENDS_HOUR = 10;
    private const MAX_WRONG_GUESSES = 5;

    public function __construct(
        private PhoneVerificationRepository $codes,
        private RateLimiter $limiter
    ) {
    }

    /** Returns an error message if sending is not allowed right now, otherwise null. */
    public function sendBlockedReason(string $phone): ?string
    {
        $since = $this->codes->secondsSinceLastSent($phone);
        if ($since !== null && $since < self::RESEND_SECONDS) {
            return 'Please wait ' . (self::RESEND_SECONDS - $since) . ' seconds before requesting another code.';
        }
        if ($this->limiter->tooManyAttempts('otp-send:phone:' . $phone, self::MAX_SENDS_PER_HOUR, 3600)
            || $this->limiter->tooManyAttempts('otp-send:ip:' . Request::clientIp(), self::MAX_IP_SENDS_HOUR, 3600)) {
            return 'Too many verification codes requested. Please try again in an hour.';
        }
        return null;
    }

    /** Creates and stores a new code, returns it so it can be sent by SMS. */
    public function issue(string $phone): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::VALID_MINUTES . ' minutes'));

        $this->codes->save($phone, $otp, $expiresAt);
        $this->limiter->hit('otp-send:phone:' . $phone, 3600);
        $this->limiter->hit('otp-send:ip:' . Request::clientIp(), 3600);

        return $otp;
    }

    /** True if the code is right and not expired. Wrong guesses are counted. */
    public function verify(string $phone, string $code): bool
    {
        $guessKey = 'otp-guess:' . $phone;
        if ($this->limiter->tooManyAttempts($guessKey, self::MAX_WRONG_GUESSES, self::VALID_MINUTES * 60)) {
            $this->codes->delete($phone);
            return false;
        }

        $record = $this->codes->findByPhone($phone);
        $valid = $record
            && hash_equals((string) $record['otp_code'], $code)
            && strtotime($record['expires_at']) >= time();

        if (!$valid) {
            $this->limiter->hit($guessKey, self::VALID_MINUTES * 60);
            return false;
        }

        $this->limiter->clear($guessKey);
        return true;
    }

    /** Call after a successful registration so the code can't be reused. */
    public function consume(string $phone): void
    {
        $this->codes->delete($phone);
    }
}
