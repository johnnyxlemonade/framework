<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark;

final class BenchmarkRun
{
    private float $startedAt;
    private float $finishedAt = 0.0;
    private int $memoryStartBytes;
    private int $allocatedMemoryStartBytes;
    private int $peakMemoryStartBytes;
    private int $peakAllocatedMemoryStartBytes;

    /**
     * @var array<string, scalar|array<int|string, mixed>|null>
     */
    private array $context;

    /**
     * @var list<array{
     *     name: string,
     *     since_previous_ms: float,
     *     memory_bytes: int,
     *     memory_delta_bytes: int,
     *     allocated_memory_bytes: int,
     *     allocated_memory_delta_bytes: int,
     *     peak_memory_bytes: int,
     *     peak_allocated_memory_bytes: int,
     *     elapsed_ms: float,
     * }>
     */
    private array $marks = [];

    /**
     * @param array<string, scalar|array<int|string, mixed>|null> $context
     */
    public function __construct(array $context = [])
    {
        $this->context = $context;
        $this->startedAt = microtime(true);
        $this->memoryStartBytes = memory_get_usage(false);
        $this->allocatedMemoryStartBytes = memory_get_usage(true);
        $this->peakMemoryStartBytes = memory_get_peak_usage(false);
        $this->peakAllocatedMemoryStartBytes = memory_get_peak_usage(true);
        $this->mark('start');
    }

    public function stop(): void
    {
        if ($this->finishedAt > 0.0) {
            return;
        }

        $this->finishedAt = microtime(true);
        $this->mark('finish');
    }

    public function mark(string $name): void
    {
        $memoryNow = memory_get_usage(false);
        $allocatedMemoryNow = memory_get_usage(true);
        $elapsedMs = round($this->elapsedMs(), 3);
        $previousElapsedMs = $this->marks === []
            ? 0.0
            : ($this->marks[array_key_last($this->marks)]['elapsed_ms'] ?? 0.0);

        $this->marks[] = [
            'name' => $name,
            'since_previous_ms' => round(max(0.0, $elapsedMs - $previousElapsedMs), 3),
            'elapsed_ms' => $elapsedMs,
            'memory_bytes' => $memoryNow,
            'memory_delta_bytes' => $memoryNow - $this->memoryStartBytes,
            'allocated_memory_bytes' => $allocatedMemoryNow,
            'allocated_memory_delta_bytes' => $allocatedMemoryNow - $this->allocatedMemoryStartBytes,
            'peak_memory_bytes' => memory_get_peak_usage(false),
            'peak_allocated_memory_bytes' => memory_get_peak_usage(true),
        ];
    }

    public function with(string $key, mixed $value): void
    {
        if (is_scalar($value) || $value === null || is_array($value)) {
            $this->context[$key] = $value;
        }
    }

    public function elapsedMs(): float
    {
        $end = $this->finishedAt > 0.0 ? $this->finishedAt : microtime(true);

        return max(0.0, ($end - $this->startedAt) * 1000);
    }

    public function memoryDeltaBytes(): int
    {
        return memory_get_usage(false) - $this->memoryStartBytes;
    }

    public function allocatedMemoryDeltaBytes(): int
    {
        return memory_get_usage(true) - $this->allocatedMemoryStartBytes;
    }

    public function peakMemoryBytes(): int
    {
        return memory_get_peak_usage(false);
    }

    public function peakAllocatedMemoryBytes(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * @return list<array{
     *     name: string,
     *     since_previous_ms: float,
     *     elapsed_ms: float,
     *     memory_bytes: int,
     *     memory_delta_bytes: int,
     *     allocated_memory_bytes: int,
     *     allocated_memory_delta_bytes: int,
     *     peak_memory_bytes: int,
     *     peak_allocated_memory_bytes: int
     * }>
     */
    public function marks(): array
    {
        return $this->marks;
    }

    /**
     * @return array{
     *     context: array<string, scalar|array<int|string, mixed>|null>,
     *     elapsed_ms: float,
     *     memory_start_bytes: int,
     *     memory_end_bytes: int,
     *     memory_delta_bytes: int,
     *     allocated_memory_start_bytes: int,
     *     allocated_memory_end_bytes: int,
     *     allocated_memory_delta_bytes: int,
     *     peak_memory_bytes: int,
     *     peak_memory_delta_bytes: int,
     *     peak_allocated_memory_bytes: int,
     *     peak_allocated_memory_delta_bytes: int,
     *     marks: list<array{
     *         name: string,
     *         since_previous_ms: float,
     *         elapsed_ms: float,
     *         memory_bytes: int,
     *         memory_delta_bytes: int,
     *         allocated_memory_bytes: int,
     *         allocated_memory_delta_bytes: int,
     *         peak_memory_bytes: int,
     *         peak_allocated_memory_bytes: int
     *     }>
     * }
     */
    public function toArray(): array
    {
        $memoryNow = memory_get_usage(false);
        $allocatedMemoryNow = memory_get_usage(true);
        $peakMemoryNow = memory_get_peak_usage(false);
        $peakAllocatedMemoryNow = memory_get_peak_usage(true);

        return [
            'context' => $this->context,
            'elapsed_ms' => round($this->elapsedMs(), 3),
            'memory_start_bytes' => $this->memoryStartBytes,
            'memory_end_bytes' => $memoryNow,
            'memory_delta_bytes' => $memoryNow - $this->memoryStartBytes,
            'allocated_memory_start_bytes' => $this->allocatedMemoryStartBytes,
            'allocated_memory_end_bytes' => $allocatedMemoryNow,
            'allocated_memory_delta_bytes' => $allocatedMemoryNow - $this->allocatedMemoryStartBytes,
            'peak_memory_bytes' => $peakMemoryNow,
            'peak_memory_delta_bytes' => $peakMemoryNow - $this->peakMemoryStartBytes,
            'peak_allocated_memory_bytes' => $peakAllocatedMemoryNow,
            'peak_allocated_memory_delta_bytes' => $peakAllocatedMemoryNow - $this->peakAllocatedMemoryStartBytes,
            'marks' => $this->marks,
        ];
    }
}
