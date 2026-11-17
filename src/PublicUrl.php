<?php

declare(strict_types=1);

namespace Buzlook;

/**
 * Builds the public URL of a label (the text encoded in its QR code).
 */
final class PublicUrl
{
    /**
     * @param array<string, mixed> $config
     */
    public static function base(array $config): string
    {
        $base = $config['public_base_url'] ?? null;
        if (!is_string($base) || $base === '') {
            $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
            $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        return rtrim($base, '/');
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function forCode(array $config, string $code): string
    {
        return self::base($config) . '/' . $code;
    }
}
