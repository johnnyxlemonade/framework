<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Logging;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Core\Logging\LoggingServiceProvider;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoggingServiceProviderTest extends TestCase
{
    public function testTypedServicesAndCompatibilityAliasesShareSingletons(): void
    {
        $container = $this->container();
        $manager = $container->get(LogManager::class);
        $app = $container->get(LoggerInterface::class);
        $benchmark = $container->get('log.benchmark');
        $config = $container->get(LoggingConfig::class);

        self::assertSame($manager, $container->get(LogManager::class));
        self::assertSame($manager, $container->get('log'));
        self::assertSame($app, $container->get(LoggerInterface::class));
        self::assertSame($app, $container->get('log.app'));
        self::assertSame($app, $manager->app());
        self::assertSame($benchmark, $container->get('log.benchmark'));
        self::assertSame($benchmark, $manager->benchmark());
        self::assertSame($config, $container->get(LoggingConfig::class));
        self::assertTrue($config->benchmarkEnabled());
    }

    private function container(): Container
    {
        $root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-logging-provider-'
            . bin2hex(random_bytes(8));
        $registry = new ConfigDefinitionRegistry();
        $registry->addDefinition(
            LoggingConfigDefinition::create()->benchmarkEnabled(),
        );

        $container = new Container();
        $container->singleton(ConfigDefinitionRegistry::class, $registry);
        $container->singleton(
            ApplicationContext::class,
            new ApplicationContext(
                Environment::Testing,
                new Path($root, $root . DIRECTORY_SEPARATOR . 'public'),
                DebugMode::disabled(),
            ),
        );
        $container->singleton(DirectoryManagerInterface::class, new DirectoryManager());
        (new LoggingServiceProvider())->register($container);

        return $container;
    }
}
