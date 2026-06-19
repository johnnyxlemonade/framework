<?php

declare(strict_types=1);

use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfigDefinition;

return BenchmarkConfigDefinition::create()
    ->injectHtmlComment();
