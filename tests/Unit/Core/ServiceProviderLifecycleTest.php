<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DefinitionServiceProviderInterface;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderLifecycle;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ServiceProviderLifecycleTest extends TestCase
{
    public function testLegacyProviderRegistrationRemainsCompatible(): void
    {
        $framework = $this->framework();

        $framework->register(new LifecycleLegacyProvider());

        self::assertInstanceOf(LifecycleLegacyService::class, $framework->container()->get(LifecycleLegacyService::class));
    }

    public function testDefinitionProviderRegistersServiceThroughBuilder(): void
    {
        $framework = $this->framework();

        $framework->register(new LifecycleDefinitionProvider());

        self::assertInstanceOf(LifecycleDefinitionService::class, $framework->container()->get(LifecycleDefinitionService::class));
    }

    public function testBootRunsAfterAllProvidersRegisterAndCanResolveAnotherProvidersService(): void
    {
        LifecycleEvents::$events = [];
        $framework = $this->framework();

        $framework->register(new LifecycleBootProvider(), new LifecycleDefinitionProvider());
        $framework->bootProviders();

        self::assertSame(['definition.register', 'boot.definition-service'], LifecycleEvents::$events);
    }

    public function testProviderImplementingDefinitionAndBootContractsWorks(): void
    {
        LifecycleEvents::$events = [];
        $framework = $this->framework();

        $framework->register(new LifecycleDefinitionAndBootProvider());
        $framework->bootProviders();

        self::assertSame(['combined.boot'], LifecycleEvents::$events);
        self::assertInstanceOf(LifecycleCombinedService::class, $framework->container()->get(LifecycleCombinedService::class));
    }

    public function testBootOrderFollowsProviderRegistrationOrder(): void
    {
        LifecycleEvents::$events = [];
        $framework = $this->framework();

        $framework->register(new LifecycleFirstBootProvider(), new LifecycleSecondBootProvider());
        $framework->bootProviders();

        self::assertSame(['first.boot', 'second.boot'], LifecycleEvents::$events);
    }

    public function testBootOnlyRunsNewlyRegisteredBootProviders(): void
    {
        LifecycleEvents::$events = [];
        $framework = $this->framework();

        $framework->register(new LifecycleFirstBootProvider());
        $framework->bootProviders();
        $framework->bootProviders();

        self::assertSame(['first.boot'], LifecycleEvents::$events);
    }

    public function testUnsupportedProviderFailsWithClearException(): void
    {
        $framework = $this->framework();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement');
        $framework->register(new LifecycleUnsupportedProvider());
    }

    public function testProviderSupportCheckRecognizesAllSupportedContracts(): void
    {
        self::assertTrue(ServiceProviderLifecycle::supports(LifecycleLegacyProvider::class));
        self::assertTrue(ServiceProviderLifecycle::supports(LifecycleDefinitionProvider::class));
        self::assertTrue(ServiceProviderLifecycle::supports(LifecycleBootProvider::class));
        self::assertFalse(ServiceProviderLifecycle::supports(LifecycleUnsupportedProvider::class));
    }

    private function framework(): Framework
    {
        return new Framework(
            new Container(),
            new ApplicationContext(Environment::Testing, new Path(__DIR__), DebugMode::disabled()),
        );
    }
}

final class LifecycleLegacyProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(LifecycleLegacyService::class, LifecycleLegacyService::class);
    }
}

final class LifecycleDefinitionProvider implements DefinitionServiceProviderInterface
{
    public function register(ContainerBuilderInterface $builder): void
    {
        LifecycleEvents::$events[] = 'definition.register';
        $builder->singleton(LifecycleDefinitionService::class, LifecycleDefinitionService::class);
    }
}

final class LifecycleBootProvider implements BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void
    {
        $container->get(LifecycleDefinitionService::class);
        LifecycleEvents::$events[] = 'boot.definition-service';
    }
}

final class LifecycleDefinitionAndBootProvider implements DefinitionServiceProviderInterface, BootableServiceProviderInterface
{
    public function register(ContainerBuilderInterface $builder): void
    {
        $builder->singleton(LifecycleCombinedService::class, LifecycleCombinedService::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $container->get(LifecycleCombinedService::class);
        LifecycleEvents::$events[] = 'combined.boot';
    }
}

final class LifecycleFirstBootProvider implements BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void
    {
        unset($container);
        LifecycleEvents::$events[] = 'first.boot';
    }
}

final class LifecycleSecondBootProvider implements BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void
    {
        unset($container);
        LifecycleEvents::$events[] = 'second.boot';
    }
}

final class LifecycleUnsupportedProvider {}

final class LifecycleLegacyService {}

final class LifecycleDefinitionService {}

final class LifecycleCombinedService {}

final class LifecycleEvents
{
    /** @var list<string> */
    public static array $events = [];
}
