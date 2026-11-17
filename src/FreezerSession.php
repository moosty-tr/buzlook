<?php

declare(strict_types=1);

namespace Buzlook;

use Buzlook\Repository\TagRepository;

/**
 * Cookie based "logged in" state that remembers which freezer label the browser has scanned.
 */
final class FreezerSession
{
    private const COOKIE_NAME = 'buzlook_freezer';

    public function __construct(
        private readonly TagRepository $tags,
        private readonly int $lifetimeSeconds,
    ) {
    }

    public function start(string $freezerCode): void
    {
        $this->writeCookie($freezerCode, time() + $this->lifetimeSeconds);
        $_COOKIE[self::COOKIE_NAME] = $freezerCode;
    }

    public function end(): void
    {
        $this->writeCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * @return array<string, mixed>|null The freezer label row, or null when nobody is logged in
     *                                   (or the freezer label has been unregistered since).
     */
    public function currentFreezer(): ?array
    {
        $code = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($code) || !CodeGenerator::isValid($code)) {
            return null;
        }

        $tag = $this->tags->findByCode($code);
        if ($tag === null || $tag['type'] !== 'freezer' || (int) $tag['enabled'] !== 1) {
            return null;
        }

        return $tag;
    }

    private function writeCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            // Lax keeps the cookie off cross-site POST requests (basic CSRF protection).
            'samesite' => 'Lax',
        ]);
    }
}
