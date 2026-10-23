<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

/**
 * Carries validated database queue storage names and bounded delivery lease policy.
 */
final readonly class QueueDatabaseConfig
{
    public const int DEFAULT_LEASE_SECONDS = 300;
    public const int DEFAULT_MAX_ATTEMPTS = 3;

    /**
     * Captures database queue storage and rejects lease settings that could disable expiry or exhaustion.
     */
    public function __construct(
        public string $table,
        public string $failedTable,
        public int $leaseSeconds = self::DEFAULT_LEASE_SECONDS,
        public int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
    ) {
        if ($this->leaseSeconds < 1) {
            throw new \InvalidArgumentException('Database queue lease_seconds must be at least 1.');
        }

        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('Database queue max_attempts must be at least 1.');
        }
    }
}
