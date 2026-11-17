<?php

declare(strict_types=1);

namespace Buzlook;

/**
 * Stateless HMAC-signed cookie session for the /admin interface, backed by api_token_hash.
 */
final class AdminSession
{
    private const COOKIE_NAME = 'buzlook_admin';
    private const LIFETIME_SECONDS = 60 * 60 * 24 * 14; // 14 days

    public function __construct(private readonly ?string $tokenHash)
    {
    }

    public function isConfigured(): bool
    {
        return is_string($this->tokenHash) && $this->tokenHash !== '';
    }

    public function isAuthenticated(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($cookie) || !str_contains($cookie, '.')) {
            return false;
        }

        [$expiresPart, $signature] = explode('.', $cookie, 2);
        if (!ctype_digit($expiresPart)) {
            return false;
        }

        $expires = (int) $expiresPart;
        if ($expires < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', 'admin:' . $expires, (string) $this->tokenHash);

        return hash_equals($expected, $signature);
    }

    public function verifyToken(string $plainToken): bool
    {
        if (!$this->isConfigured() || $plainToken === '') {
            return false;
        }

        return hash_equals((string) $this->tokenHash, hash('sha256', $plainToken));
    }

    public function start(): void
    {
        $expires = time() + self::LIFETIME_SECONDS;
        $value = $expires . '.' . hash_hmac('sha256', 'admin:' . $expires, (string) $this->tokenHash);

        $this->writeCookie($value, $expires);
        $_COOKIE[self::COOKIE_NAME] = $value;
    }

    public function end(): void
    {
        $this->writeCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    public function csrfToken(): string
    {
        $cookie = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');

        return hash_hmac('sha256', 'csrf:' . $cookie, (string) $this->tokenHash);
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->csrfToken(), $token);
    }

    private function writeCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
