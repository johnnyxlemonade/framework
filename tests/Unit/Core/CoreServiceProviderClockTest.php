<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\CoreServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CoreServiceProviderClockTest extends TestCase
{
    public function testRegistersClockAliasAndInterface(): void
    {
        $container = new Container();
        $container->singleton(Config::class, new Config([
            'app' => ['timezone' => 'UTC'],
        ]));

        (new CoreServiceProvider())->register($container);

        self::assertTrue($container->isBound(ClockInterface::class));
        self::assertTrue($container->isBound('clock'));
        self::assertSame(
            $container->get(ClockInterface::class),
            $container->get('clock'),
        );
    }

    public function testInvalidTimezoneThrowsDuringProviderRegistration(): void
    {
        $container = new Container();
        $container->singleton(Config::class, new Config([
            'app' => ['timezone' => 'Invalid/Timezone'],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid configured timezone in app.timezone');

        (new CoreServiceProvider())->register($container);
    }
}
