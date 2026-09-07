<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Cli\Config\CommandsConfig;
use Lemonade\Framework\Cli\Config\CommandsConfigDefinition;
use Lemonade\Framework\Cli\Config\CommandsConfigResolver;
use Lemonade\Framework\Container\Config\ContainerConfig;
use Lemonade\Framework\Container\Config\ContainerConfigDefinition;
use Lemonade\Framework\Container\Config\ContainerConfigResolver;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class CoreConfigurationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(Config::class, new Config());
        $container->singleton(ConfigDefinitionRegistry::class, new ConfigDefinitionRegistry());
        $container->singleton(ContainerConfigResolver::class, ContainerConfigResolver::class);
        $container->singleton(ContainerConfig::class, static function (ContainerInterface $container): ContainerConfig {
            return $container
                ->get(ContainerConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ContainerConfigDefinition::moduleKey(),
                    ContainerConfigDefinition::class,
                ));
        });
        $container->singleton(AppConfigResolver::class, AppConfigResolver::class);
        $container->singleton(AppConfig::class, static function (ContainerInterface $container): AppConfig {
            return $container
                ->get(AppConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    AppConfigDefinition::moduleKey(),
                    AppConfigDefinition::class,
                ));
        });
        $container->singleton(FrameworkConfigResolver::class, FrameworkConfigResolver::class);
        $container->singleton(FrameworkConfig::class, static function (ContainerInterface $container): FrameworkConfig {
            return $container
                ->get(FrameworkConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    FrameworkConfigDefinition::moduleKey(),
                    FrameworkConfigDefinition::class,
                ));
        });
        $container->singleton(ProvidersConfigResolver::class, ProvidersConfigResolver::class);
        $container->singleton(ProvidersConfig::class, static function (ContainerInterface $container): ProvidersConfig {
            return $container
                ->get(ProvidersConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ProvidersConfigDefinition::moduleKey(),
                    ProvidersConfigDefinition::class,
                ));
        });
        $container->singleton(CommandsConfigResolver::class, CommandsConfigResolver::class);
        $container->singleton(CommandsConfig::class, static function (ContainerInterface $container): CommandsConfig {
            return $container
                ->get(CommandsConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    CommandsConfigDefinition::moduleKey(),
                    CommandsConfigDefinition::class,
                ));
        });
    }
}
