<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Diagnostics;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Core\Logging\LogManager;
use PHPUnit\Framework\TestCase;

final class ExceptionLoggerTest extends TestCase
{
    public function testLogNeverThrowsWithMinimalContainer(): void
    {
        $container = new Container();
        $logger = new ExceptionLogger($container, $this->context());

        $logger->log(new \RuntimeException('boom'), 'kernel');
        self::addToAssertionCount(1);
    }

    public function testFallbackRespectsErrorLogEnabledFalse(): void
    {
        $container = new Container();
        $container->singleton(Config::class, new Config([
            'error' => [
                'log' => [
                    'enabled' => false,
                ],
            ],
        ]));

        $logger = new ExceptionLogger($container, $this->context());

        $logger->log(new \RuntimeException('boom'), 'kernel');
        self::addToAssertionCount(1);
    }

    public function testLogNeverThrowsWhenLogManagerBindingFails(): void
    {
        $container = new Container();
        $container->singleton(
            LogManager::class,
            static function (): never {
                throw new \RuntimeException('cannot create log manager');
            },
        );

        $logger = new ExceptionLogger($container, $this->context());

        $logger->log(new \RuntimeException('boom'), 'kernel');
        self::addToAssertionCount(1);
    }

    private function context(): ApplicationContext
    {
        return new ApplicationContext(
            Environment::Testing,
            new Path(__DIR__),
            DebugMode::disabled(),
        );
    }
}
