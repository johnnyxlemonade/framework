<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class BenchmarkServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(Benchmark::class, Benchmark::class);
        $container->singleton(BenchmarkResponseInjector::class, BenchmarkResponseInjector::class);
    }
}
