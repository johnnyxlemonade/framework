<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http;

use InvalidArgumentException;

/**
 * Freezes one completed HTTP interval and its SQL totals without retaining request data.
 *
 * PHP peak memory is the script's peak reported by PHP, not a request-local peak
 * in a persistent worker. Values are supplied by the lifecycle owner and never re-read.
 */
final readonly class HttpMetrics
{
    /**
     * Accepts finite, non-negative measurements in milliseconds and bytes.
     *
     * @throws InvalidArgumentException When a measurement is negative or non-finite.
     */
    public function __construct(
        private float $durationMs,
        private int $phpPeakMemoryBytes,
        private int $sqlCount,
        private float $sqlMs,
    ) {
        if (!is_finite($durationMs) || !is_finite($sqlMs)
            || $durationMs < 0 || $phpPeakMemoryBytes < 0 || $sqlCount < 0 || $sqlMs < 0) {
            throw new InvalidArgumentException('HTTP metrics must be finite and non-negative.');
        }
    }

    /**
     * Returns the completed HTTP interval in milliseconds, without reading the clock again.
     */
    public function durationMs(): float
    {
        return $this->durationMs;
    }

    /**
     * Returns the PHP script/process peak in bytes sampled at completion.
     */
    public function phpPeakMemoryBytes(): int
    {
        return $this->phpPeakMemoryBytes;
    }

    /**
     * Returns the captured SQL attempt count, including failed executions.
     */
    public function sqlCount(): int
    {
        return $this->sqlCount;
    }

    /**
     * Returns the captured SQL milliseconds, excluding connection setup and observer execution.
     */
    public function sqlMs(): float
    {
        return $this->sqlMs;
    }
}
