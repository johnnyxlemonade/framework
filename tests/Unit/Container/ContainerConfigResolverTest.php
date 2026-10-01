<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Config\AutowireMode;
use Lemonade\Framework\Container\Config\ContainerConfigDefinition;
use Lemonade\Framework\Container\Config\ContainerConfigResolver;
use PHPUnit\Framework\TestCase;

final class ContainerConfigResolverTest extends TestCase
{
    public function testDefaultsToPermissiveAutowiring(): void
    {
        $config = (new ContainerConfigResolver())->resolve(ContainerConfigDefinition::create());

        self::assertSame(AutowireMode::Permissive, $config->autowire);
    }

    public function testResolvesStrictAutowiringFromDefinition(): void
    {
        $config = (new ContainerConfigResolver())->resolve(
            ContainerConfigDefinition::create()->autowire('strict'),
        );

        self::assertSame(AutowireMode::Strict, $config->autowire);
    }

    public function testInvalidAutowireModeRetainsThePreviousValidMode(): void
    {
        $config = (new ContainerConfigResolver())->resolve(
            ContainerConfigDefinition::create()->autowire('strict'),
            ContainerConfigDefinition::create()->autowire('unsupported'),
        );

        self::assertSame(AutowireMode::Strict, $config->autowire);
    }
}
