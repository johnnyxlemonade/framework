<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session;

use InvalidArgumentException;

/**
 * Defines the canonical probability for request-driven session garbage collection.
 */
final readonly class SessionGarbageCollectionPolicy
{
    /**
     * Gives the standard 100-entry file/database batches capacity for two candidates per request.
     */
    public const int DEFAULT_PROBABILITY = 2;
    public const int DEFAULT_DIVISOR = 100;

    /**
     * Defines how many outcomes trigger collection within the positive outcome range.
     */
    public function __construct(
        private int $probability = self::DEFAULT_PROBABILITY,
        private int $divisor = self::DEFAULT_DIVISOR,
    ) {
        if ($probability < 0 || $divisor < 1 || $probability > $divisor) {
            throw new InvalidArgumentException(
                'Session GC probability must be between zero and its positive divisor.',
            );
        }
    }

    /**
     * Returns the number of outcomes that trigger collection.
     */
    public function probability(): int
    {
        return $this->probability;
    }

    /**
     * Returns the total number of possible collection outcomes.
     */
    public function divisor(): int
    {
        return $this->divisor;
    }

    /**
     * Selects whether the current request should perform opportunistic collection.
     */
    public function shouldRun(): bool
    {
        if ($this->probability === 0) {
            return false;
        }

        return random_int(1, $this->divisor) <= $this->probability;
    }
}
