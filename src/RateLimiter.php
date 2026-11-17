<?php

declare(strict_types=1);

namespace Buzlook;

use PDO;

/**
 * Failure based rate limiting stored in SQLite.
 *
 * Every failure (e.g. scanning an unknown label) is counted per key inside a time window.
 * When the limit is reached the key is blocked for a while; the block is checked before any lookup,
 * so a blocked client can not keep probing.
 */
final class RateLimiter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Derives the client key from the request. Only REMOTE_ADDR is trusted unless a proxy header is
     * configured (then the LAST entry is used: the one appended by your own proxy).
     * IPv6 addresses are reduced to their /64 prefix so rotating inside a range does not help.
     *
     * @param array<string, mixed> $server
     */
    public static function clientKey(array $server, ?string $proxyHeader = null): string
    {
        $address = (string) ($server['REMOTE_ADDR'] ?? '');

        if ($proxyHeader !== null && $proxyHeader !== '' && !empty($server[$proxyHeader])) {
            $parts = array_map('trim', explode(',', (string) $server[$proxyHeader]));
            $candidate = end($parts);
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                $address = $candidate;
            }
        }

        $packed = @inet_pton($address);
        if ($packed === false) {
            return 'unknown';
        }
        if (strlen($packed) === 16) {
            return 'v6:' . bin2hex(substr($packed, 0, 8));
        }

        return $address;
    }

    /**
     * @return int Seconds the key is still blocked (0 = not blocked).
     */
    public function retryAfter(string $key): int
    {
        $statement = $this->pdo->prepare('SELECT blocked_until FROM rate_limits WHERE key = :key');
        $statement->execute(['key' => $key]);
        $blockedUntil = (int) $statement->fetchColumn();

        return max(0, $blockedUntil - time());
    }

    public function recordFailure(string $key, int $maxFailures, int $windowSeconds, int $blockSeconds): void
    {
        $now = time();

        // IMMEDIATE takes the write lock up front so concurrent failures are counted exactly.
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $statement = $this->pdo->prepare('SELECT failures, window_start FROM rate_limits WHERE key = :key');
            $statement->execute(['key' => $key]);
            $row = $statement->fetch();

            $failures = 0;
            $windowStart = $now;
            if ($row !== false && $now - (int) $row['window_start'] <= $windowSeconds) {
                $failures = (int) $row['failures'];
                $windowStart = (int) $row['window_start'];
            }

            $failures++;
            $blockedUntil = 0;
            if ($failures >= $maxFailures) {
                $blockedUntil = $now + $blockSeconds;
                $failures = 0;
                $windowStart = $now;
            }

            $upsert = $this->pdo->prepare(
                'INSERT INTO rate_limits (key, failures, window_start, blocked_until)
                 VALUES (:key, :failures, :window_start, :blocked_until)
                 ON CONFLICT(key) DO UPDATE SET
                    failures = excluded.failures,
                    window_start = excluded.window_start,
                    blocked_until = MAX(rate_limits.blocked_until, excluded.blocked_until)'
            );
            $upsert->execute([
                'key' => $key,
                'failures' => $failures,
                'window_start' => $windowStart,
                'blocked_until' => $blockedUntil,
            ]);

            // Housekeeping: forget stale keys now and then.
            if (random_int(1, 100) === 1) {
                $this->pdo->prepare('DELETE FROM rate_limits WHERE blocked_until < :now AND window_start < :old')
                    ->execute(['now' => $now, 'old' => $now - 86400]);
            }

            $this->pdo->exec('COMMIT');
        } catch (\Throwable $exception) {
            $this->pdo->exec('ROLLBACK');
            throw $exception;
        }
    }
}
