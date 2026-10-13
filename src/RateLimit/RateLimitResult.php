<?php

declare(strict_types=1);

namespace Lemonade\Framework\RateLimit;

/**
 * Carries an immutable bucket decision after a status check or recorded attempt.
 */
final readonly class RateLimitResult
{
    /**
     * Initializes the decision returned to a limiter consumer.
     */
    public function __construct(
        private bool $allowed,
        private int $remaining,
        private int $retryAfterSeconds,
    ) {
    }

    /**
     * Indicates whether the bucket currently permits the protected operation.
     */
    public function allowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Returns the remaining attempts in the active fixed window.
     */
    public function remaining(): int
    {
        return $this->remaining;
    }

    /**
     * Returns the number of seconds until a blocked bucket becomes available.
     */
    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
