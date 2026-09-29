<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Cli\CommandContext;
use Lemonade\Framework\Cli\CommandInput;
use Lemonade\Framework\Cli\CommandOutput;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Exception\ProviderConstructionException;
use Lemonade\Framework\Core\ProviderFactory;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Queue\JobContext;
use Lemonade\Framework\Queue\QueuedMessage;
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

    /**
     * @dataProvider runtimeOnlyScopeDependencyProvider
     * @param class-string $dependency
     * @param class-string<ServiceProviderInterface> $providerClass
     */
    public function testRejectsCommandAndJobDependenciesEvenWhenTheyAreBound(string $dependency, string $providerClass): void
    {
        $container = new Container();
        $container->singleton($dependency, new \stdClass());

        $this->expectException(ProviderConstructionException::class);
        $this->expectExceptionMessage('runtime-only');

        (new ProviderFactory($container))->create($providerClass);
    }

    /**
     * @return iterable<string, array{class-string, class-string<ServiceProviderInterface>}>
     */
    public static function runtimeOnlyScopeDependencyProvider(): iterable
    {
        yield 'command context' => [CommandContext::class, CommandContextDependencyProvider::class];
        yield 'command input' => [CommandInput::class, CommandInputDependencyProvider::class];
        yield 'command output' => [CommandOutput::class, CommandOutputDependencyProvider::class];
        yield 'job context' => [JobContext::class, JobContextDependencyProvider::class];
        yield 'queued message' => [QueuedMessage::class, QueuedMessageDependencyProvider::class];
    }
}

final class NoConstructorProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void {}
}

final class ProviderBootstrapDependency {}

final class BootstrapDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ProviderBootstrapDependency $dependency,
    ) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class ProviderUnboundDependency {}

final class UnboundDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ProviderUnboundDependency $dependency,
    ) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class ProviderScopedDependency {}

final class ScopedDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ProviderScopedDependency $dependency,
    ) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class RequestDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ServerRequestInterface $request,
    ) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class ContainerDependencyProvider implements ServiceProviderInterface
{
    public function __construct(
        public readonly ContainerInterface $container,
    ) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class CommandContextDependencyProvider implements ServiceProviderInterface
{
    public function __construct(public readonly CommandContext $context) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class CommandInputDependencyProvider implements ServiceProviderInterface
{
    public function __construct(public readonly CommandInput $input) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class CommandOutputDependencyProvider implements ServiceProviderInterface
{
    public function __construct(public readonly CommandOutput $output) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class JobContextDependencyProvider implements ServiceProviderInterface
{
    public function __construct(public readonly JobContext $context) {}

    public function register(ContainerBuilderInterface $container): void {}
}

final class QueuedMessageDependencyProvider implements ServiceProviderInterface
{
    public function __construct(public readonly QueuedMessage $message) {}

    public function register(ContainerBuilderInterface $container): void {}
}
