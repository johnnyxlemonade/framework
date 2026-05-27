<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Component\ComponentServiceProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Database\DatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Mysql\MysqlDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Odbc\OdbcDatabaseServiceProvider;
use Lemonade\Framework\Event\EventServiceProvider;
use Lemonade\Framework\Localization\LocalizationServiceProvider;
use Lemonade\Framework\Queue\QueueServiceProvider;
use Lemonade\Framework\Routing\RoutingServiceProvider;
use Lemonade\Framework\Security\SecurityServiceProvider;
use Lemonade\Framework\Session\SessionServiceProvider;
use Lemonade\Framework\Upload\UploadServiceProvider;
use Lemonade\Framework\Validation\ValidationServiceProvider;
use Lemonade\Framework\View\ViewServiceProvider;
use PHPUnit\Framework\TestCase;

final class FrameworkTest extends TestCase
{
    public function testFrameworkLoadsDefaultFrameworkProvidersConfig(): void
    {
        $framework = $this->framework();
        $config = $framework->container()->get(Config::class);
        $providers = $config->get('framework.providers');

        self::assertIsArray($providers);
        self::assertSame([
            LocalizationServiceProvider::class,
            RoutingServiceProvider::class,
            SecurityServiceProvider::class,
            DatabaseServiceProvider::class,
            MysqlDatabaseServiceProvider::class,
            OdbcDatabaseServiceProvider::class,
            SessionServiceProvider::class,
            ComponentServiceProvider::class,
            ValidationServiceProvider::class,
            UploadServiceProvider::class,
            ViewServiceProvider::class,
            EventServiceProvider::class,
            QueueServiceProvider::class,
        ], $providers);
    }

    public function testAppConfigCanReplaceFrameworkProvidersList(): void
    {
        $framework = $this->framework();
        $config = $framework->container()->get(Config::class);

        $framework->config([
            'framework' => [
                'providers' => [
                    RoutingServiceProvider::class,
                ],
            ],
        ]);

        self::assertSame(
            [RoutingServiceProvider::class],
            $config->get('framework.providers'),
        );
    }

    private function framework(): Framework
    {
        $container = new Container();
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(__DIR__),
            DebugMode::disabled(),
        );

        return new Framework($container, $context);
    }
}
