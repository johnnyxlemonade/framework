<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Exception\ProviderConstructionException;
use Lemonade\Framework\Core\ProviderFactory;
use Lemonade\Framework\Core\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class ProviderFactoryTest extends TestCase
{
    public function testCreatesProviderWithoutConstructor(): void
    {
        $provider = (new ProviderFactory(new Container()))->create(NoConstructorProvider::class);

        self::assertInstanceOf(NoConstructorProvider::class, $provider);
    }

    public function testInjectsExplicitlyBoundBootstrapService(): void
    {
        $container = new Container();
        $dependency = new ProviderBootstrapDependency();
        $container->singleton(ProviderBootstrapDependency::class, $dependency);

        $provider = (new ProviderFactory($container))->create(BootstrapDependencyProvider::class);

        self::assertInstanceOf(BootstrapDependencyProvider::class, $provider);
        self::assertSame($dependency, $provider->dependency);
    }

    public function testRejectsDependencyThatIsNotExplicitlyBound(): void
    {
        $this->expectException(ProviderConstructionException::class);
        $this->expectExceptionMessage('not an explicitly bound bootstrap service');

        (new ProviderFactory(new Container()))->create(UnboundDependencyProvider::class);
    }

    public function testRejectsScopedDependencyDuringBootstrap(): void
    {
        $container = new Container();
        $container->scoped(ProviderScopedDependency::class, ProviderScopedDependency::class);

        $this->expectException(ProviderConstructionException::class);
        $this->expectExceptionMessage('scope-local and unavailable during bootstrap');

        (new ProviderFactory($container))->create(ScopedDependencyProvider::class);
    }

    public function testRejectsRequestDependencyEvenWhenItIsBound(): void
    {
        $container = new Container();
        $container->singleton(ServerRequestInterface::class, static fn (): ServerRequestInterface => self::createStub(ServerRequestInterface::class));

        $this->expectException(ProviderConstructionException::class);
        $this->expectExceptionMessage('runtime-only');

        (new ProviderFactory($container))->create(RequestDependencyProvider::class);
    }

    public function testRejectsContainerDependency(): void
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);

        $this->expectException(ProviderConstructionException::class);
        $this->expectExceptionMessage('runtime-only');

        (new ProviderFactory($container))->create(ContainerDependencyProvider::class);
    }
}

final class NoConstructorProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void {}
}

final class ProviderBootstrapDependency {}

final class BootstrapDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ProviderBootstrapDependency $dependency,
    ) {}

    public function register(ContainerInterface $container): void {}
}

final class ProviderUnboundDependency {}

final class UnboundDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ProviderUnboundDependency $dependency,
    ) {}

    public function register(ContainerInterface $container): void {}
}

final class ProviderScopedDependency {}

final class ScopedDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ProviderScopedDependency $dependency,
    ) {}

    public function register(ContainerInterface $container): void {}
}

final class RequestDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ServerRequestInterface $request,
    ) {}

    public function register(ContainerInterface $container): void {}
}

final class ContainerDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ContainerInterface $container,
    ) {}

    public function register(ContainerInterface $container): void {}
}
