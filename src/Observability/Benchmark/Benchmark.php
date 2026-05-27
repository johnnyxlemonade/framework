<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark;

final class Benchmark
{
    private ?BenchmarkRun $current = null;

    /**
     * @param array<string, scalar|array<int|string, mixed>|null> $context
     */
    public function start(array $context = []): BenchmarkRun
    {
        $this->current = new BenchmarkRun($context);

        return $this->current;
    }

    public function current(): ?BenchmarkRun
    {
        return $this->current;
    }

    /**
     * @param array<string, scalar|array<int|string, mixed>|null> $context
     */
    public function currentOrStart(array $context = []): BenchmarkRun
    {
        if ($this->current instanceof BenchmarkRun) {
            foreach ($context as $key => $value) {
                $this->current->with($key, $value);
            }

            return $this->current;
        }

        return $this->start($context);
    }

    /**
     * @param array<string, scalar|array<int|string, mixed>|null> $context
     */
    public function createRun(array $context = []): BenchmarkRun
    {
        return $this->start($context);
    }
}
