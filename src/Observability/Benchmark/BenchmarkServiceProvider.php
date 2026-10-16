<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfigDefinition;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfigResolver;

/**
 * Registers global benchmark configuration and the root lifecycle coordinator.
 */
final readonly class BenchmarkServiceProvider implements ServiceProviderInterface
{
    /**
     * Keeps configuration and lifecycle identity shared while stateless helpers remain autowired.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(BenchmarkConfig::class, static function (ContainerInterface $container): BenchmarkConfig {
            return $container
                ->get(BenchmarkConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    BenchmarkConfigDefinition::moduleKey(),
                    BenchmarkConfigDefinition::class,
                ));
        });
        $container->singleton(Benchmark::class, Benchmark::class);
    }
}
