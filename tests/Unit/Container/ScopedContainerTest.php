<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\ScopedContainerClosedException;
use Lemonade\Framework\Container\Exception\ScopedServiceRequestedFromRootException;
use Lemonade\Framework\Container\Exception\SingletonDependsOnScopedServiceException;
use Lemonade\Framework\Container\ScopeKind;
use PHPUnit\Framework\TestCase;

final class ScopedContainerTest extends TestCase
{
    public function testRootCannotResolveScopedService(): void
    {
        $container = new Container();
        $container->scoped('scoped.service', ScopedTestDependency::class);

        $this->expectException(ScopedServiceRequestedFromRootException::class);
        $this->expectExceptionMessage('scoped.service');
        $container->get('scoped.service');
    }

    public function testScopedServiceIsCachedWithinOneScopeAndNotSharedWithAnother(): void
    {
        $container = new Container();
        $container->scoped('scoped.service', static fn(): \stdClass => new \stdClass());
        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Command);

        $first = $firstScope->get('scoped.service');

        self::assertSame(ScopeKind::Request, $firstScope->kind());
        self::assertSame($first, $firstScope->get('scoped.service'));
        self::assertNotSame($first, $secondScope->get('scoped.service'));
    }

    public function testCloseIsIdempotentAndPreventsFurtherResolution(): void
    {
        $container = new Container();
        $container->scoped('scoped.service', ScopedTestDependency::class);
        $scope = $container->beginScope(ScopeKind::Job);
        $scope->bindScopedInstance('scope.local', new ScopedLocalValue());
        $scope->get('scoped.service');

        $scope->close();
        $scope->close();

        self::assertFalse($scope->has('scope.local'));
        self::assertFalse($container->has('scope.local'));

        $this->expectException(ScopedContainerClosedException::class);
        $this->expectExceptionMessage('job');
        $scope->get('scoped.service');
    }

    public function testScopedInstanceIsVisibleOnlyToItsOwnScope(): void
    {
        $container = new Container();
        $value = new ScopedLocalValue();
        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Command);
        $firstScope->bindScopedInstance('scope.local', $value);

        self::assertSame($value, $firstScope->get('scope.local'));
        self::assertTrue($firstScope->has('scope.local'));
        self::assertTrue($firstScope->isBound('scope.local'));
        self::assertFalse($container->has('scope.local'));
        self::assertFalse($container->isBound('scope.local'));
        self::assertFalse($secondScope->has('scope.local'));
        self::assertFalse($secondScope->isBound('scope.local'));
    }

    public function testScopedInstanceTakesPrecedenceOverRootBinding(): void
    {
        $container = new Container();
        $rootValue = new ScopedLocalValue();
        $scopeValue = new ScopedLocalValue();
        $container->singleton('scope.local', $rootValue);
        $scope = $container->beginScope(ScopeKind::Request);
        $scope->bindScopedInstance('scope.local', $scopeValue);

        self::assertSame($scopeValue, $scope->get('scope.local'));
        self::assertSame($rootValue, $container->get('scope.local'));
    }

    public function testScopeBindsItselfWithoutChangingRootContainerBinding(): void
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);
        $scope = $container->beginScope(ScopeKind::Request);

        self::assertSame($scope, $scope->get(ContainerInterface::class));
        self::assertSame($scope, $scope->get(\Lemonade\Framework\Container\ScopedContainerInterface::class));
        self::assertSame($container, $container->get(ContainerInterface::class));
    }

    public function testRootSingletonDoesNotCaptureScopeLocalContainerBinding(): void
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);
        $container->singleton('root.singleton', static fn(ContainerInterface $container): ContainerInterface => $container->get(ContainerInterface::class));
        $singleton = $container->get('root.singleton');
        $scope = $container->beginScope(ScopeKind::Request);

        self::assertSame($container, $singleton);
        self::assertSame($container, $scope->get('root.singleton'));
        self::assertSame($scope, $scope->get(ContainerInterface::class));
    }

    public function testSingletonsRemainSharedAcrossScopes(): void
    {
        $container = new Container();
        $container->singleton('singleton.service', static fn(): \stdClass => new \stdClass());

        $first = $container->beginScope(ScopeKind::Request)->get('singleton.service');
        $second = $container->beginScope(ScopeKind::Command)->get('singleton.service');

        self::assertSame($first, $second);
    }

    public function testRootFactoryAndDecoratorReceiveRootContainer(): void
    {
        $factoryContainer = null;
        $decoratorContainer = null;
        $builder = new ContainerBuilder();
        $builder->singleton('service', static function (ContainerInterface $container) use (&$factoryContainer): \stdClass {
            $factoryContainer = $container;

            return new \stdClass();
        });
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$decoratorContainer): mixed {
            $decoratorContainer = $container;

            return $inner;
        });
        $container = new Container($builder);

        $container->get('service');

        self::assertSame($container, $factoryContainer);
        self::assertSame($container, $decoratorContainer);
    }

    public function testScopedFactoryAndDecoratorReceiveScopeAndCacheDecoratedResult(): void
    {
        $factoryContainer = null;
        $decoratorContainer = null;
        $builder = new ContainerBuilder();
        $builder->scoped(ScopedTestDependency::class, ScopedTestDependency::class);
        $builder->scoped('decorated.service', static function (ContainerInterface $container) use (&$factoryContainer): ScopedTestDependency {
            $factoryContainer = $container;
            $dependency = $container->get(ScopedTestDependency::class);
            assert($dependency instanceof ScopedTestDependency);

            return $dependency;
        });
        $builder->decorate('decorated.service', static function (ContainerInterface $container, mixed $inner) use (&$decoratorContainer): ScopedDecoratedResult {
            $decoratorContainer = $container;
            $dependency = $container->get(ScopedTestDependency::class);
            assert($dependency instanceof ScopedTestDependency);

            return new ScopedDecoratedResult($inner, $dependency);
        });
        $container = new Container($builder);
        $scope = $container->beginScope(ScopeKind::Request);

        $first = $scope->get('decorated.service');
        $second = $scope->get('decorated.service');

        self::assertInstanceOf(ScopedDecoratedResult::class, $first);
        self::assertSame($scope, $factoryContainer);
        self::assertSame($scope, $decoratorContainer);
        self::assertSame($first, $second);
        self::assertSame($first->inner, $first->dependency);
    }

    public function testScopedFactoryCanReadScopeLocalInstance(): void
    {
        $builder = new ContainerBuilder();
        $builder->set('factory.service', static fn(ContainerInterface $container): mixed => $container->get('scope.local'));
        $container = new Container($builder);
        $scope = $container->beginScope(ScopeKind::Request);
        $value = new ScopedLocalValue();
        $scope->bindScopedInstance('scope.local', $value);

        self::assertSame($value, $scope->get('factory.service'));
    }

    public function testScopedDecoratorCanReadScopeLocalInstance(): void
    {
        $builder = new ContainerBuilder();
        $builder->set('decorated.service', static fn(): \stdClass => new \stdClass());
        $builder->decorate('decorated.service', static fn(ContainerInterface $container, mixed $inner): mixed => $container->get('scope.local'));
        $container = new Container($builder);
        $scope = $container->beginScope(ScopeKind::Request);
        $value = new ScopedLocalValue();
        $scope->bindScopedInstance('scope.local', $value);

        self::assertSame($value, $scope->get('decorated.service'));
    }

    public function testContextualScopedDependencyResolvesOnlyWithinScopeAndThroughAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->scoped('scoped.canonical', ScopedTestDependency::class);
        $builder->alias('scoped.alias', 'scoped.canonical');
        $builder->when(ScopedContextualConsumer::class)->needs(ScopedTestContract::class)->give('scoped.alias');
        $container = new Container($builder);
        $scope = $container->beginScope(ScopeKind::Request);

        $consumer = $scope->get(ScopedContextualConsumer::class);

        self::assertSame($scope->get('scoped.canonical'), $consumer->dependency);
        self::assertSame($consumer->dependency, $scope->get('scoped.alias'));

        $this->expectException(ScopedServiceRequestedFromRootException::class);
        $container->get(ScopedContextualConsumer::class);
    }

    public function testTransientDependingOnScopedServiceResolvesOnlyWithinScope(): void
    {
        $container = new Container();
        $container->scoped(ScopedTestDependency::class, ScopedTestDependency::class);
        $container->set(ScopedTransientConsumer::class, ScopedTransientConsumer::class);
        $scope = $container->beginScope(ScopeKind::Request);

        $first = $scope->get(ScopedTransientConsumer::class);
        $second = $scope->get(ScopedTransientConsumer::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->dependency, $second->dependency);

        $this->expectException(ScopedServiceRequestedFromRootException::class);
        $container->get(ScopedTransientConsumer::class);
    }

    public function testSingletonDependingOnScopedServiceFailsThroughDirectDependency(): void
    {
        $container = new Container();
        $container->scoped(ScopedTestDependency::class, ScopedTestDependency::class);
        $container->singleton(ScopedSingletonConsumer::class, ScopedSingletonConsumer::class);

        $this->expectException(SingletonDependsOnScopedServiceException::class);
        $this->expectExceptionMessage(ScopedSingletonConsumer::class . ' -> ' . ScopedTestDependency::class);
        $container->get(ScopedSingletonConsumer::class);
    }

    public function testSingletonDependingOnScopedServiceFailsThroughContextualAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->scoped('scoped.canonical', ScopedTestDependency::class);
        $builder->alias('scoped.alias', 'scoped.canonical');
        $builder->singleton(ScopedContextualSingletonConsumer::class, ScopedContextualSingletonConsumer::class);
        $builder->when(ScopedContextualSingletonConsumer::class)->needs(ScopedTestContract::class)->give('scoped.alias');
        $container = new Container($builder);

        $this->expectException(SingletonDependsOnScopedServiceException::class);
        $this->expectExceptionMessage(ScopedContextualSingletonConsumer::class . ' -> scoped.canonical');
        $container->get(ScopedContextualSingletonConsumer::class);
    }

    public function testReentrantScopedResolutionUsesCanonicalServiceIds(): void
    {
        $builder = new ContainerBuilder();
        $builder->scoped('scoped.canonical', static fn(ContainerInterface $container): mixed => $container->get('scoped.alias'));
        $builder->alias('scoped.alias', 'scoped.canonical');
        $scope = (new Container($builder))->beginScope(ScopeKind::Request);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('scoped.canonical -> scoped.canonical');
        $scope->get('scoped.alias');
    }
}

interface ScopedTestContract {}

final class ScopedTestDependency implements ScopedTestContract {}

final class ScopedLocalValue {}

final class ScopedDecoratedResult
{
    public function __construct(
        public mixed $inner,
        public ScopedTestDependency $dependency,
    ) {}
}

final class ScopedContextualConsumer
{
    public function __construct(public ScopedTestContract $dependency) {}
}

final class ScopedTransientConsumer
{
    public function __construct(public ScopedTestDependency $dependency) {}
}

final class ScopedSingletonConsumer
{
    public function __construct(public ScopedTestDependency $dependency) {}
}

final class ScopedContextualSingletonConsumer
{
    public function __construct(public ScopedTestContract $dependency) {}
}
