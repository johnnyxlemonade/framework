<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark;

/**
 * Coordinates one active benchmark run across root-owned runtime services.
 *
 * The coordinator is a root singleton, while each HTTP or CLI lifecycle must
 * start a fresh run and end it in a finally block.
 */
final class Benchmark
{
    private ?BenchmarkRun $current = null;

    /**
     * Stops any attached run and replaces it with a fresh lifecycle run.
     *
     * @param array<string, scalar|array<int|string, mixed>|null> $context
     */
    public function start(array $context = []): BenchmarkRun
    {
        $this->current?->stop();
        $this->current = new BenchmarkRun($context);

        return $this->current;
    }

    /**
     * Returns the run currently attached to this lifecycle coordinator.
     */
    public function current(): ?BenchmarkRun
    {
        return $this->current;
    }

    /**
     * Returns the active run or starts a fresh one when none is running.
     *
     * A stopped run is never reused.
     *
     * @param array<string, scalar|array<int|string, mixed>|null> $context
     */
    public function currentOrStart(array $context = []): BenchmarkRun
    {
        if ($this->current instanceof BenchmarkRun && !$this->current->isStopped()) {
            foreach ($context as $key => $value) {
                $this->current->with($key, $value);
            }

            return $this->current;
        }

        return $this->start($context);
    }

    /**
     * Starts a fresh run for callers using the legacy creation name.
     *
     * @param array<string, scalar|array<int|string, mixed>|null> $context
     */
    public function createRun(array $context = []): BenchmarkRun
    {
        return $this->start($context);
    }

    /**
     * Stops the attached run while keeping it available for final reporting.
     */
    public function stop(): ?BenchmarkRun
    {
        $this->current?->stop();

        return $this->current;
    }

    /**
     * Detaches the current run without changing its stopped state.
     */
    public function reset(): void
    {
        $this->current = null;
    }

    /**
     * Stops and detaches the current run at an outer runtime lifecycle boundary.
     */
    public function end(): ?BenchmarkRun
    {
        $run = $this->stop();
        $this->reset();

        return $run;
    }

    /**
     * Adds one database query measurement to the active run when present.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function recordDatabaseQuery(
        string $sql,
        array $bindings,
        float $elapsedMs,
        bool $captureDetails,
    ): void {
        if ($this->current === null || $this->current->isStopped()) {
            return;
        }

        $this->current->recordDatabaseQuery($sql, $bindings, $elapsedMs, $captureDetails);
    }

    /**
     * Adds database connection time to the active run when present.
     */
    public function recordDatabaseConnection(float $elapsedMs): void
    {
        if ($this->current === null || $this->current->isStopped()) {
            return;
        }

        $this->current->recordDatabaseConnection($elapsedMs);
    }
}
