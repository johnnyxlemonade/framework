<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ProviderContainerAssertions;
use PHPUnit\Framework\TestCase;

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

}

final class TestProvider {}
