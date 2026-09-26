<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ProviderContainerAssertions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProviderContainerAssertionsTest extends TestCase
{
    public function testBuilderReturnsTheConcreteBuilderContainer(): void
    {
        $container = new Container();

        self::assertSame(
            $container,
            ProviderContainerAssertions::builder($container, TestProvider::class, 'request-scoped services'),
        );
    }

    public function testBuilderRejectsAContainerWithoutBuilderCapabilities(): void
    {
        $container = self::createStub(ContainerInterface::class);
        assert($container instanceof ContainerInterface);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            TestProvider::class . ' requires a container implementing ' . ContainerBuilderInterface::class . ' to register request-scoped services.',
        );

        ProviderContainerAssertions::builder($container, TestProvider::class, 'request-scoped services');
    }
}

final class TestProvider {}
