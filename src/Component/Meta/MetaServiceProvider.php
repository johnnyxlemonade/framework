<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

use Lemonade\Framework\Component\Meta\Config\MetaConfig;
use Lemonade\Framework\Component\Meta\Config\MetaConfigDefinition;
use Lemonade\Framework\Component\Meta\Config\MetaConfigResolver;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class MetaServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MetaConfigResolver::class, MetaConfigResolver::class);
        $container->singleton(MetaConfig::class, static function (ContainerInterface $container): MetaConfig {
            return $container
                ->get(MetaConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    MetaConfigDefinition::moduleKey(),
                    MetaConfigDefinition::class,
                ));
        });

        $container->singleton(MetaComponent::class, static function (ContainerInterface $container): MetaComponent {
            return new MetaComponent($container->get(MetaConfig::class));
        });
    }
}
