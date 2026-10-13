<?php

declare(strict_types=1);

namespace Lemonade\Framework\RateLimit;

/**
 * Defines a strictly positive maximum number of attempts within one fixed window.
 */
final readonly class RateLimitPolicy
{
    /**
     * Validates the fixed-window limits used by a limiter operation.
     */
    public function __construct(
        private int $attempts,
        private int $windowSeconds,
    ) {
        if ($this->attempts < 1) {
            throw new \InvalidArgumentException('Rate limit attempts must be greater than zero.');
        }

        if ($this->windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit window seconds must be greater than zero.');
        }
    }

    /**
     * Returns the maximum number of attempts represented by this policy.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Returns the fixed window duration in seconds.
     */
    public function windowSeconds(): int
    {
        return $this->windowSeconds;
    }
}
