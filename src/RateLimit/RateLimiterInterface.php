<?php

declare(strict_types=1);

namespace Lemonade\Framework\RateLimit;

/**
 * Limits repeated events by opaque caller-defined keys without exposing storage details.
 */
interface RateLimiterInterface
{
    /**
     * Returns the current bucket state without recording an attempt.
     *
     * Scope is validated diagnostic metadata only; it does not affect bucket identity.
     */
    public function status(string $key, string $scope, RateLimitPolicy $policy): RateLimitResult;

    /**
     * Atomically records one attempt and returns the resulting bucket state.
     *
     * Scope records safe diagnostic metadata without affecting bucket identity.
     */
    public function hit(string $key, string $scope, RateLimitPolicy $policy): RateLimitResult;

    /**
     * Removes the bucket associated with one opaque key.
     */
    public function clear(string $key): void;
}
