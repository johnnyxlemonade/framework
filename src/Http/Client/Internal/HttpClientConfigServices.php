<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Client\Internal;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Http\Config\HttpClientConfig;
use Lemonade\Framework\Http\Config\HttpClientConfigDefinition;
use Lemonade\Framework\Http\Config\HttpClientConfigResolver;

/**
 * Registers the transport-neutral configuration shared by optional HTTP client adapters.
 *
 * @internal
 */
final readonly class HttpClientConfigServices
{
    private function __construct()
    {
    }

    /**
     * Adds the lazy singleton binding for the normalized runtime snapshot.
     */
    public static function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(
            HttpClientConfig::class,
            static function (ContainerInterface $container): HttpClientConfig {
                $resolver = $container->get(HttpClientConfigResolver::class);
                $registry = $container->get(ConfigDefinitionRegistry::class);
                $definitions = $registry->typedEntriesFor(
                    HttpClientConfigDefinition::moduleKey(),
                    HttpClientConfigDefinition::class,
                );

                return $resolver->resolve(...$definitions);
            },
        );
    }
}
