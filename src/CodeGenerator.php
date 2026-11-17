<?php

declare(strict_types=1);

namespace Buzlook;

/**
 * Generates short, URL friendly tag codes using the Base58 alphabet
 * (no 0, O, I, l so printed codes cannot be misread).
 */
final class CodeGenerator
{
    public const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /** Regex fragment (no delimiters) matching a valid code of any sensible length. */
    public const PATTERN = '[1-9A-HJ-NP-Za-km-z]{1,64}';

    public const MIN_LENGTH = 4;
    public const MAX_LENGTH = 64;

    /** Codes that would clash with real directories of the web root. */
    private const RESERVED = ['assets', 'uploads', 'data', 'src', 'bin', 'templates'];

    public static function isValid(string $code): bool
    {
        return preg_match('/^' . self::PATTERN . '$/D', $code) === 1;
    }

    public static function generate(int $length): string
    {
        $length = max(self::MIN_LENGTH, min(self::MAX_LENGTH, $length));
        $maxIndex = strlen(self::ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::ALPHABET[random_int(0, $maxIndex)];
            }
        } while (in_array(strtolower($code), self::RESERVED, true));

        return $code;
    }
}
