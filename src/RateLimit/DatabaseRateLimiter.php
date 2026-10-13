<?php

declare(strict_types=1);

namespace Lemonade\Framework\RateLimit;

use Lemonade\Framework\Database\Database;

/**
 * Stores fixed-window buckets atomically in a MySQL or MariaDB table.
 */
final class DatabaseRateLimiter implements RateLimiterInterface
{
    private const string TABLE = 'framework_rate_limit';

    /**
     * Initializes database persistence and the application secret used for bucket HMACs.
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $secret,
    ) {
        if ($this->secret === '') {
            throw new \LogicException('APP_SECRET must be configured before using the database rate limiter.');
        }
    }

    /**
     * Reads the current bucket state without creating or modifying a database row.
     */
    public function status(string $key, RateLimitPolicy $policy): RateLimitResult
    {
        $row = $this->row($this->keyHash($key));
        if ($row === null || $this->isExpired($row)) {
            return new RateLimitResult(true, $policy->attempts(), 0);
        }

        return $this->result($row, $policy);
    }

    /**
     * Atomically records an attempt and locks the resulting row until its decision is read.
     */
    public function hit(string $key, RateLimitPolicy $policy): RateLimitResult
    {
        $keyHash = $this->keyHash($key);

        return $this->database->transaction(function () use ($keyHash, $policy): RateLimitResult {
            $this->database->statement(
                'INSERT INTO ' . self::TABLE . ' (key_hash, attempts, expires_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)) '
                . 'ON DUPLICATE KEY UPDATE '
                . 'attempts = IF(expires_at <= UTC_TIMESTAMP(), 1, attempts + 1), '
                . 'expires_at = IF(expires_at <= UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), expires_at)',
                [$keyHash, $policy->windowSeconds(), $policy->windowSeconds()],
            );

            $row = $this->row($keyHash, true);
            if ($row === null) {
                throw new \LogicException('Rate limit bucket is unavailable after an atomic upsert.');
            }

            return $this->result($row, $policy);
        });
    }

    /**
     * Deletes the HMAC-addressed bucket for one opaque key.
     */
    public function clear(string $key): void
    {
        $this->database->statement(
            'DELETE FROM ' . self::TABLE . ' WHERE key_hash = ?',
            [$this->keyHash($key)],
        );
    }

    /**
     * Derives a binary HMAC identity without persisting the original opaque key.
     */
    private function keyHash(string $key): string
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Rate limit key cannot be empty.');
        }

        return hash_hmac('sha256', $key, $this->secret, true);
    }

    /**
     * Reads one bucket and optionally locks it in the current transaction.
     *
     * @return array{attempts:int|string,expires_at:string}|null
     */
    private function row(string $keyHash, bool $forUpdate = false): ?array
    {
        $rows = $this->database->select(
            'SELECT attempts, expires_at FROM ' . self::TABLE . ' WHERE key_hash = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''),
            [$keyHash],
        );

        if ($rows === []) {
            return null;
        }

        $attempts = $rows[0]['attempts'] ?? null;
        $expiresAt = $rows[0]['expires_at'] ?? null;
        if ((!is_int($attempts) && !is_string($attempts)) || !is_string($expiresAt)) {
            throw new \LogicException('Rate limit bucket contains an invalid database payload.');
        }

        return [
            'attempts' => $attempts,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Determines whether the stored fixed window has ended.
     *
     * @param array{attempts:int|string,expires_at:string} $row
     */
    private function isExpired(array $row): bool
    {
        return $this->expiresAtTimestamp($row) <= time();
    }

    /**
     * Builds the externally visible decision for an active bucket.
     *
     * @param array{attempts:int|string,expires_at:string} $row
     */
    private function result(array $row, RateLimitPolicy $policy): RateLimitResult
    {
        $attempts = (int) $row['attempts'];
        $allowed = $attempts < $policy->attempts();

        return new RateLimitResult(
            allowed: $allowed,
            remaining: $allowed ? max(0, $policy->attempts() - $attempts) : 0,
            retryAfterSeconds: $allowed ? 0 : max(1, $this->expiresAtTimestamp($row) - time()),
        );
    }

    /**
     * Converts the database UTC expiry value into a Unix timestamp.
     *
     * @param array{attempts:int|string,expires_at:string} $row
     */
    private function expiresAtTimestamp(array $row): int
    {
        $timestamp = strtotime($row['expires_at'] . ' UTC');
        if ($timestamp === false) {
            throw new \LogicException('Rate limit bucket contains an invalid expiry timestamp.');
        }

        return $timestamp;
    }
}
