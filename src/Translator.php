<?php

declare(strict_types=1);

namespace Buzlook;

/**
 * Looks up UI texts in lang/{code}.php files.
 *
 * The language is picked from the browser's Accept-Language header among the available
 * lang files; when nothing matches, the configured default language is used. Missing keys
 * fall back to the default language and finally to English.
 */
final class Translator
{
    private const FALLBACK_LANGUAGE = 'en';

    private string $language;

    /** @var array<string, string> */
    private array $messages;

    public function __construct(string $langPath, string $defaultLanguage, ?string $acceptLanguage)
    {
        $available = self::availableLanguages($langPath);
        $default = self::match([$defaultLanguage], $available) ?? self::FALLBACK_LANGUAGE;

        $this->language = self::match(self::parseAcceptLanguage($acceptLanguage), $available) ?? $default;

        $messages = [];
        foreach (array_unique([self::FALLBACK_LANGUAGE, $default, $this->language]) as $language) {
            $file = $langPath . '/' . $language . '.php';
            if (is_file($file)) {
                $messages = array_replace($messages, (array) require $file);
            }
        }
        $this->messages = $messages;
    }

    public function language(): string
    {
        return $this->language;
    }

    /**
     * @param array<string, scalar> $params Values for {placeholders}.
     */
    public function t(string $key, array $params = []): string
    {
        $text = $this->messages[$key] ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * Messages whose key starts with the prefix (the prefix is stripped); used to hand texts to JavaScript.
     *
     * @return array<string, string>
     */
    public function withPrefix(string $prefix): array
    {
        $result = [];
        foreach ($this->messages as $key => $text) {
            if (str_starts_with($key, $prefix)) {
                $result[substr($key, strlen($prefix))] = $text;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string> lowercase language tag => language file name
     */
    private static function availableLanguages(string $langPath): array
    {
        $available = [];
        foreach (glob($langPath . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]+)*$/', $name) === 1) {
                $available[strtolower($name)] = $name;
            }
        }

        return $available;
    }

    /**
     * @return list<string> Language tags ordered by the browser's preference.
     */
    private static function parseAcceptLanguage(?string $header): array
    {
        $entries = [];
        foreach (explode(',', (string) $header) as $index => $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(trim($pieces[0]));
            if ($tag === '' || $tag === '*') {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parameter, $match) === 1) {
                    $quality = (float) $match[1];
                }
            }
            if ($quality > 0) {
                $entries[] = ['tag' => $tag, 'quality' => $quality, 'index' => $index];
            }
        }

        usort($entries, static fn (array $a, array $b): int => [$b['quality'], $a['index']] <=> [$a['quality'], $b['index']]);

        return array_column($entries, 'tag');
    }

    /**
     * @param list<string> $wanted
     * @param array<string, string> $available
     */
    private static function match(array $wanted, array $available): ?string
    {
        foreach ($wanted as $tag) {
            $tag = strtolower($tag);
            if (isset($available[$tag])) {
                return $available[$tag];
            }
            $primary = explode('-', $tag)[0];
            if (isset($available[$primary])) {
                return $available[$primary];
            }
        }

        return null;
    }
}
