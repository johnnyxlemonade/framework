<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\DefinitionServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\Exception\InvalidProviderDependencyException;
use Lemonade\Framework\Core\Exception\ProviderDependencyCycleException;
use Lemonade\Framework\Core\Exception\ProviderDependencyNotFoundException;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\ProviderDependencyResolver;
use Lemonade\Framework\Core\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;

final class ProviderDependencyResolverTest extends TestCase
{
    public function testProvidersWithoutDependenciesKeepConfiguredOrder(): void
    {
        $providers = (new ProviderDependencyResolver())->sort([
            new GraphFirstProvider(),
            new GraphSecondProvider(),
            new GraphThirdProvider(),
        ]);

        self::assertSame(
            [GraphFirstProvider::class, GraphSecondProvider::class, GraphThirdProvider::class],
            $this->providerClasses($providers),
        );
    }

    public function testDependentProviderIsSortedAfterLegacyDependency(): void
    {
        $providers = (new ProviderDependencyResolver())->sort([
            new GraphLegacyDependentProvider(),
            new GraphLegacyProvider(),
        ]);

        self::assertSame(
            [GraphLegacyProvider::class, GraphLegacyDependentProvider::class],
            $this->providerClasses($providers),
        );
    }

    public function testDefinitionAndBootOnlyProvidersCanBeDependencies(): void
    {
        $resolver = new ProviderDependencyResolver();

        self::assertSame(
            [GraphDefinitionProvider::class, GraphDefinitionDependentProvider::class],
            $this->providerClasses($resolver->sort([
                new GraphDefinitionDependentProvider(),
                new GraphDefinitionProvider(),
            ])),
        );
        self::assertSame(
            [GraphBootOnlyProvider::class, GraphBootOnlyDependentProvider::class],
            $this->providerClasses($resolver->sort([
                new GraphBootOnlyDependentProvider(),
                new GraphBootOnlyProvider(),
            ])),
        );
    }

    public function testLifecycleUsesDependencyOrderForRegisterAndBoot(): void
    {
        GraphLifecycleEvents::$events = [];
        $framework = new Framework(
            new Container(),
            new ApplicationContext(Environment::Testing, new Path(__DIR__), DebugMode::disabled()),
        );

        $framework->register(new GraphLifecycleDependentProvider(), new GraphLifecycleDependencyProvider());
        $framework->bootProviders();

        self::assertSame([
            'dependency.register',
            'dependent.register',
            'dependency.boot',
            'dependent.boot',
        ], GraphLifecycleEvents::$events);
    }

    public function testMissingDependencyThrowsClearException(): void
    {
        $this->expectException(ProviderDependencyNotFoundException::class);
        $this->expectExceptionMessage(GraphLegacyProvider::class);

        (new ProviderDependencyResolver())->sort([new GraphMissingDependencyProvider()]);
    }

    public function testDependencyCycleThrowsWithFullPath(): void
    {
        $this->expectException(ProviderDependencyCycleException::class);
        $this->expectExceptionMessage(
            GraphCycleFirstProvider::class
            . ' -> ' . GraphCycleSecondProvider::class
            . ' -> ' . GraphCycleThirdProvider::class
            . ' -> ' . GraphCycleFirstProvider::class,
        );

        (new ProviderDependencyResolver())->sort([
            new GraphCycleFirstProvider(),
            new GraphCycleSecondProvider(),
            new GraphCycleThirdProvider(),
        ]);
    }

    public function testDependencyOnInvalidProviderClassThrowsClearException(): void
    {
        $this->expectException(InvalidProviderDependencyException::class);
        $this->expectExceptionMessage(GraphNotAProvider::class);

        (new ProviderDependencyResolver())->sort([new GraphInvalidDependencyProvider()]);
    }

    /**
     * @param list<object> $providers
     * @return list<class-string>
     */
    private function providerClasses(array $providers): array
    {
        return array_map(static fn(object $provider): string => $provider::class, $providers);
    }
}

final class GraphFirstProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphSecondProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphThirdProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphLegacyProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphLegacyDependentProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphLegacyProvider::class];
    }

    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphDefinitionProvider implements DefinitionServiceProviderInterface
{
    public function register(ContainerBuilderInterface $builder): void
    {
        unset($builder);
    }
}

final class GraphDefinitionDependentProvider implements DefinitionServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphDefinitionProvider::class];
    }

    public function register(ContainerBuilderInterface $builder): void
    {
        unset($builder);
    }
}

final class GraphBootOnlyProvider implements BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphBootOnlyDependentProvider implements BootableServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphBootOnlyProvider::class];
    }

    public function boot(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphLifecycleDependencyProvider implements DefinitionServiceProviderInterface, BootableServiceProviderInterface
{
    public function register(ContainerBuilderInterface $builder): void
    {
        unset($builder);
        GraphLifecycleEvents::$events[] = 'dependency.register';
    }

    public function boot(ContainerInterface $container): void
    {
        unset($container);
        GraphLifecycleEvents::$events[] = 'dependency.boot';
    }
}

final class GraphLifecycleDependentProvider implements DefinitionServiceProviderInterface, BootableServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphLifecycleDependencyProvider::class];
    }

    public function register(ContainerBuilderInterface $builder): void
    {
        unset($builder);
        GraphLifecycleEvents::$events[] = 'dependent.register';
    }

    public function boot(ContainerInterface $container): void
    {
        unset($container);
        GraphLifecycleEvents::$events[] = 'dependent.boot';
    }
}

final class GraphMissingDependencyProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphLegacyProvider::class];
    }

    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphCycleFirstProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphCycleSecondProvider::class];
    }

    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphCycleSecondProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphCycleThirdProvider::class];
    }

    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphCycleThirdProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphCycleFirstProvider::class];
    }

    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphInvalidDependencyProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [GraphNotAProvider::class];
    }

    public function register(ContainerInterface $container): void
    {
        unset($container);
    }
}

final class GraphNotAProvider {}

final class GraphLifecycleEvents
{
    /** @var list<string> */
    public static array $events = [];
}
