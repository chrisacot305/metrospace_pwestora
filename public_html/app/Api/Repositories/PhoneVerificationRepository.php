<?php
namespace Api\Repositories;

/** Table: phone_verifications (SMS OTP codes) */
final class PhoneVerificationRepository extends Repository
{
    public function save(string $phone, string $otpCode, string $expiresAt): void
    {
        $this->execute(
            'INSERT INTO phone_verifications (phone, otp_code, expires_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), expires_at = VALUES(expires_at)',
            [$phone, $otpCode, $expiresAt]
        );
    }

    public function findByPhone(string $phone): ?array
    {
        return $this->fetchOne('SELECT * FROM phone_verifications WHERE phone = ? LIMIT 1', [$phone]);
    }

    /** Seconds since a code was last sent to this phone, or null if none. */
    public function secondsSinceLastSent(string $phone): ?int
    {
        $seconds = $this->fetchValue(
            'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM phone_verifications WHERE phone = ?',
            [$phone]
        );
        return $seconds === false || $seconds === null ? null : (int) $seconds;
    }

    public function delete(string $phone): void
    {
        $this->execute('DELETE FROM phone_verifications WHERE phone = ?', [$phone]);
    }
}
