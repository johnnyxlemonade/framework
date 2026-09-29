<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\FrameworkConfig;
use Lemonade\Framework\Core\Config\FrameworkConfigDefinition;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\DefinitionServiceProviderInterface;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\ServiceProviderInterface;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FrameworkConfigResolverTest extends TestCase
{
    public function testMissingProviderClassThrowsLogicException(): void
    {
        $framework = $this->framework();
        $framework->config(
            FrameworkConfigDefinition::create()->providers(['Definitely\\Missing\\Provider']),
        );

        $this->expectException(LogicException::class);
        $framework->container()->get(FrameworkConfig::class);
    }

    public function testProviderWithoutInterfaceThrowsLogicException(): void
    {
        $framework = $this->framework();
        $framework->config(
            FrameworkConfigDefinition::create()->providers([NotAFrameworkServiceProvider::class]),
        );

        $this->expectException(LogicException::class);
        $framework->container()->get(FrameworkConfig::class);
    }

    public function testValidProviderClassPassesValidation(): void
    {
        $framework = $this->framework();
        $framework->config(
            FrameworkConfigDefinition::create()->providers([ValidFrameworkServiceProvider::class]),
        );

        self::assertSame(
            [ValidFrameworkServiceProvider::class],
            $framework->container()->get(FrameworkConfig::class)->providers,
        );
    }

    public function testDefinitionProviderClassPassesValidation(): void
    {
        $framework = $this->framework();
        $framework->config(
            FrameworkConfigDefinition::create()->providers([ValidFrameworkDefinitionServiceProvider::class]),
        );

        self::assertSame(
            [ValidFrameworkDefinitionServiceProvider::class],
            $framework->container()->get(FrameworkConfig::class)->providers,
        );
    }

    private function framework(): Framework
    {
        return new Framework(
            new Container(),
            new ApplicationContext(
                Environment::Testing,
                new Path(__DIR__),
                DebugMode::disabled(),
            ),
        );
    }
}

final class NotAFrameworkServiceProvider {}

final class ValidFrameworkServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void {}
}

final class ValidFrameworkDefinitionServiceProvider implements DefinitionServiceProviderInterface
{
    public function register(ContainerBuilderInterface $builder): void {}
}
