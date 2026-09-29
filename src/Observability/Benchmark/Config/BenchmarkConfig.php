<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark\Config;

final readonly class BenchmarkConfig
{
    public function __construct(
        public bool $injectHtmlComment,
    ) {
    }
}
