<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigResolver;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Registers logging policy, named channels, and the default PSR-3 application logger.
 */
final class LoggingServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds root-owned logging services and retains compatibility aliases without duplicating instances.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(LoggingConfigResolver::class, LoggingConfigResolver::class);
        $container->singleton(LoggingConfig::class, static function (ContainerInterface $container): LoggingConfig {
            return $container
                ->get(LoggingConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    LoggingConfigDefinition::moduleKey(),
                    LoggingConfigDefinition::class,
                ));
        });
        $container->singleton(LogFilePathResolver::class, LogFilePathResolver::class);
        $container->singleton(LogManager::class, LogManager::class);
        $container->alias('log', LogManager::class);

        $container->singleton(
            LoggerInterface::class,
            static fn(ContainerInterface $container): LoggerInterface => $container->get(LogManager::class)->app(),
        );
        $container->alias('log.app', LoggerInterface::class);

        $container->singleton(
            'log.benchmark',
            static fn(ContainerInterface $container): LoggerInterface => $container->get(LogManager::class)->benchmark(),
        );
    }
}
