<?php
namespace Api\Repositories;

/** Table: otp_verifications (email OTP codes, used by the older register_otp.php) */
final class EmailVerificationRepository extends Repository
{
    public function save(string $email, string $otpCode): void
    {
        $this->execute(
            'INSERT INTO otp_verifications (email, otp_code, created_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), created_at = NOW()',
            [$email, $otpCode]
        );
    }

    /** The stored code if it was created within the last $validMinutes, else null. */
    public function findValidCode(string $email, int $validMinutes): ?string
    {
        $code = $this->fetchValue(
            'SELECT otp_code FROM otp_verifications
             WHERE email = ? AND created_at > (NOW() - INTERVAL ? MINUTE)
             LIMIT 1',
            [$email, $validMinutes]
        );
        return $code === false ? null : (string) $code;
    }

    public function delete(string $email): void
    {
        $this->execute('DELETE FROM otp_verifications WHERE email = ?', [$email]);
    }
}
