<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Instrumentation\Internal;

use Lemonade\Framework\Database\Instrumentation\DatabaseObserverInterface;
use LogicException;
use Throwable;

/**
 * Lets long-lived connections report into a fresh, explicitly bounded SQL capture.
 *
 * @internal For synchronous sequential lifecycles. No collector or observer is retained
 * outside collect(); nested or overlapping captures on one instance are rejected.
 */
final class DatabaseInstrumentation
{
    private ?DatabaseMeasurements $measurements = null;
    private ?DatabaseObserverInterface $observer = null;

    /**
     * Supplies fresh totals to one synchronous consumer and always releases the active capture.
     *
     * The consumer may copy totals into HttpMetrics before returning or in its own finally block.
     * Root-owned drivers use this instance; the HTTP boundary is its runtime capture owner.
     *
     * @template T
     * @param callable(DatabaseMeasurements): T $operation
     * @return T
     * @throws LogicException When a capture is already active on this instance.
     * @throws Throwable Propagates the consumer's original failure after releasing capture state.
     */
    public function collect(callable $operation, ?DatabaseObserverInterface $observer = null): mixed
    {
        if ($this->measurements !== null) {
            throw new LogicException('Database instrumentation already has an active capture.');
        }

        $measurements = new DatabaseMeasurements();
        $this->measurements = $measurements;
        $this->observer = $observer;

        try {
            return $operation($measurements);
        } finally {
            $this->observer = null;
            $this->measurements = null;
        }
    }

    /**
     * Measures one execution attempt only while a consumer is active, preserving its return or exception.
     *
     * Drivers must open the connection before this callback and fetch rows afterwards.
     * Keep prepare, binding, execution and existing execution-result bookkeeping inside it.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws Throwable Propagates the operation's original failure after recording the attempt.
     */
    public function query(string $sql, callable $operation): mixed
    {
        $measurements = $this->measurements;
        if ($measurements === null) {
            return $operation();
        }

        $startedNs = hrtime(true);
        $successful = false;

        try {
            $result = $operation();
            $successful = true;

            return $result;
        } finally {
            $durationMs = (hrtime(true) - $startedNs) / 1_000_000;
            $measurements->recordQuery($durationMs);

            try {
                $this->observer?->queryCompleted($sql, $durationMs, $successful);
            } catch (Throwable) {
                $this->observer = null;
            }
        }
    }

    /**
     * Measures connection setup only for an observer; it never contributes to SQL aggregates.
     *
     * Call only when opening a connection, not when returning a cached native handle.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws Throwable Propagates the connection operation's original failure.
     */
    public function connection(callable $operation): mixed
    {
        if ($this->observer === null) {
            return $operation();
        }

        $startedNs = hrtime(true);
        $successful = false;

        try {
            $result = $operation();
            $successful = true;

            return $result;
        } finally {
            $durationMs = (hrtime(true) - $startedNs) / 1_000_000;

            $this->notifyConnection($durationMs, $successful);
        }
    }

    private function notifyConnection(float $durationMs, bool $successful): void
    {
        // The operation may already have disabled the observer through a nested query.
        try {
            $this->observer?->connectionCompleted($durationMs, $successful);
        } catch (Throwable) {
            $this->observer = null;
        }
    }
}
