<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Health;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;

final class FrameworkHealthConfigSnapshot
{
    public function __construct(
        public readonly ApiConfig $api,
        public readonly AppConfig $app,
        public readonly ?CorsConfig $cors,
        public readonly BenchmarkConfig $benchmark,
    ) {}
}
