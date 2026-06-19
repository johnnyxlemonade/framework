<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigResolver;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Psr\Log\LoggerInterface;

final class LoggingServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
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
        $container->singleton('log', LogManager::class);

        $container->singleton('log.app', static function (ContainerInterface $container): LoggerInterface {
            /** @var LogManager $manager */
            $manager = $container->get(LogManager::class);
            $logger = $manager->app();

            $container->setDiagnosticLogger($logger);

            return $logger;
        });

        $container->singleton(LoggerInterface::class, static function (ContainerInterface $container): LoggerInterface {
            /** @var LoggerInterface $logger */
            $logger = $container->get('log.app');

            return $logger;
        });

        $container->singleton('log.benchmark', static function (ContainerInterface $container): LoggerInterface {
            return $container->get(LogManager::class)->benchmark();
        });
    }
}
