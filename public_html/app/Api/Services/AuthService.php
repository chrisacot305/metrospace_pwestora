<?php
namespace Api\Services;

use Api\Http\Request;
use Api\Http\Response;
use Api\Repositories\UserRepository;
use Api\Support\AppConfig;
use Api\Support\Token;

/**
 * Works out who is calling the API from their Bearer token.
 */
final class AuthService
{
    public function __construct(private UserRepository $users)
    {
    }

    /** The logged-in lessee, or a 401 error. */
    public function requireLessee(bool $allowDemoFallback = false): array
    {
        $user = $this->currentLessee();
        if ($user) {
            // Suspended in Super Admin → User Management: the app must stop working too
            // (before, suspension only blocked the website).
            if (($user['status'] ?? '') === 'suspended') {
                Response::error('Your account has been suspended. Please contact Pwestora support.', 403);
            }
            return $user;
        }

        // Old "no token needed" shortcut — only when demo mode is switched on (API_DEMO_MODE=true in .env).
        if ($allowDemoFallback && AppConfig::demoMode()) {
            $user = $this->users->findFirstActiveLessee();
            if ($user) {
                return $user;
            }
        }

        Response::error('Missing, invalid, or expired authorization token. Please log in again.', 401);
        exit;
    }

    /** The logged-in lessee, or null if no / bad token (for public endpoints). */
    public function currentLessee(): ?array
    {
        $token = Request::bearerToken();
        if ($token === null) {
            return null;
        }
        return $this->users->findLesseeByTokenHash(Token::hash($token));
    }

    /** Creates a new token, stores its hash, and returns the plain token for the app. */
    public function issueToken(int $userId): string
    {
        $token = Token::generate();
        $this->users->updateApiToken($userId, Token::hash($token));
        return $token;
    }
}
