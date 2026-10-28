<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Instrumentation\Internal;

/**
 * Accumulates only query count and duration for one explicitly observed operation scope.
 *
 * @internal Owned by DatabaseInstrumentation; never registered as a singleton.
 */
final class DatabaseMeasurements
{
    private int $queryCount = 0;
    private float $queryMs = 0.0;

    /**
     * Adds one measured attempt without retaining SQL, bindings or results.
     */
    public function recordQuery(float $durationMs): void
    {
        $this->queryCount++;
        $this->queryMs += $durationMs;
    }

    /**
     * Returns the number of measured query attempts, including failures.
     */
    public function queryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Returns unrounded query milliseconds, excluding connection setup and observer execution.
     */
    public function queryMs(): float
    {
        return $this->queryMs;
    }
}
