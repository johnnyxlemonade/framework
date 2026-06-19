<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark\Config;

final class BenchmarkConfig
{
    public function __construct(
        public readonly bool $injectHtmlComment,
    ) {}
}
