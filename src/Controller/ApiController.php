<?php

declare(strict_types=1);

namespace Buzlook\Controller;

use Buzlook\CodeGenerator;
use Buzlook\PublicUrl;
use Buzlook\RateLimiter;
use Buzlook\Repository\TagRepository;

/**
 * Admin JSON API used to create and (un)register labels.
 *
 * Authentication: "Authorization: Bearer <token>"; the server only stores the SHA-256 hash of the token.
 */
final class ApiController
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly TagRepository $tags,
        private readonly array $config,
        private readonly RateLimiter $rateLimiter,
        private readonly string $clientKey,
    ) {
    }

    /** POST /api/v1/tags/generate  {"count": 100, "length": 6?}  -> new unique codes, nothing is stored. */
    public function generate(): void
    {
        $body = $this->authenticatedBody();
        $codes = $this->generateUniqueCodes($this->requestedCount($body), $this->requestedLength($body));

        $this->json(200, ['count' => count($codes), 'tags' => $this->describe($codes)]);
    }

    /** POST /api/v1/tags  {"count": 100, "length": 6?}  -> new codes, created and registered. */
    public function create(): void
    {
        $body = $this->authenticatedBody();
        $codes = $this->generateUniqueCodes($this->requestedCount($body), $this->requestedLength($body));
        $this->tags->register($codes);

        $this->json(201, ['count' => count($codes), 'tags' => $this->describe($codes)]);
    }

    /** POST /api/v1/tags/register  {"codes": ["..."]}  -> registers previously generated codes. */
    public function register(): void
    {
        $codes = $this->requestedCodes($this->authenticatedBody());

        $this->json(200, $this->tags->register($codes));
    }

    /** POST /api/v1/tags/unregister  {"codes": ["..."]}  -> disables codes (history is kept). */
    public function unregister(): void
    {
        $codes = $this->requestedCodes($this->authenticatedBody());

        $this->json(200, $this->tags->unregister($codes));
    }

    /**
     * @return array<string, mixed> The decoded JSON request body.
     */
    private function authenticatedBody(): array
    {
        $hash = $this->config['api_token_hash'] ?? null;
        if (!is_string($hash) || $hash === '') {
            $this->json(503, ['error' => 'The API is disabled: no API token has been configured.']);
        }

        $limits = $this->config['rate_limits']['api'];
        $limitKey = 'api:' . $this->clientKey;
        $retryAfter = $this->rateLimiter->retryAfter($limitKey);
        if ($retryAfter > 0) {
            header('Retry-After: ' . $retryAfter);
            $this->json(429, ['error' => 'Too many failed attempts. Try again later.', 'retry_after' => $retryAfter]);
        }

        $header = $this->authorizationHeader();
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        if ($token === '' || !hash_equals($hash, hash('sha256', $token))) {
            $this->rateLimiter->recordFailure($limitKey, (int) $limits['max_failures'], (int) $limits['window'], (int) $limits['block']);
            header('WWW-Authenticate: Bearer');
            $this->json(401, ['error' => 'Missing or invalid API token.']);
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $this->json(400, ['error' => 'The request body must be a JSON object.']);
        }

        return $body;
    }

    private function authorizationHeader(): string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                return (string) $_SERVER[$key];
            }
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requestedCount(array $body): int
    {
        $max = (int) $this->config['api_max_batch'];
        $count = $body['count'] ?? null;
        if (!is_int($count) || $count < 1 || $count > $max) {
            $this->json(422, ['error' => "\"count\" must be an integer between 1 and {$max}."]);
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requestedLength(array $body): int
    {
        $length = $body['length'] ?? (int) $this->config['code_length'];
        if (!is_int($length) || $length < CodeGenerator::MIN_LENGTH || $length > CodeGenerator::MAX_LENGTH) {
            $this->json(422, ['error' => '"length" must be an integer between ' . CodeGenerator::MIN_LENGTH . ' and ' . CodeGenerator::MAX_LENGTH . '.']);
        }

        return $length;
    }

    /**
     * @param array<string, mixed> $body
     * @return list<string>
     */
    private function requestedCodes(array $body): array
    {
        $max = (int) $this->config['api_max_batch'];
        $codes = $body['codes'] ?? null;
        if (!is_array($codes) || $codes === [] || count($codes) > $max) {
            $this->json(422, ['error' => "\"codes\" must be a list of 1 to {$max} codes."]);
        }

        $unique = [];
        $invalid = [];
        foreach ($codes as $code) {
            if (!is_string($code) || !CodeGenerator::isValid($code)) {
                $invalid[] = is_string($code) ? $code : gettype($code);
                continue;
            }
            $unique[$code] = true;
        }
        if ($invalid !== []) {
            $this->json(422, ['error' => 'Some codes are not valid Base58 codes.', 'invalid' => array_slice($invalid, 0, 20)]);
        }

        return array_map('strval', array_keys($unique));
    }

    /**
     * @return list<string>
     */
    private function generateUniqueCodes(int $count, int $length): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $candidates = [];
            while (count($candidates) + count($codes) < $count) {
                $candidates[CodeGenerator::generate($length)] = true;
            }
            $candidates = array_diff(array_keys($candidates), array_keys($codes));
            $taken = array_flip($this->tags->existingCodes($candidates));
            foreach ($candidates as $candidate) {
                if (!isset($taken[$candidate])) {
                    $codes[$candidate] = true;
                }
            }
        }

        return array_map('strval', array_keys($codes));
    }

    /**
     * @param list<string> $codes
     * @return list<array{code: string, url: string}>
     */
    private function describe(array $codes): array
    {
        $base = PublicUrl::base($this->config);

        return array_map(static fn (string $code): array => ['code' => $code, 'url' => $base . '/' . $code], $codes);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
