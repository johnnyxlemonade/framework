<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

final class ContainerReentrantResolutionTest extends TestCase
{
    public function testFactoryCannotResolveItsOwnCanonicalService(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('factory.service', static fn(ContainerInterface $container): mixed => $container->get('factory.service'));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: factory.service -> factory.service');

        (new Container($builder))->get('factory.service');
    }

    public function testFactoryCannotResolveItselfThroughAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('factory.service', static fn(ContainerInterface $container): mixed => $container->get('factory.alias'));
        $builder->alias('factory.alias', 'factory.service');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: factory.service -> factory.service');

        (new Container($builder))->get('factory.alias');
    }

    public function testCallableDecoratorCannotResolveItsOwnCanonicalService(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('decorated.service', ReentrantResolutionBaseService::class);
        $builder->decorate(
            'decorated.service',
            static fn(ContainerInterface $container, mixed $inner): mixed => $container->get('decorated.service'),
        );

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: decorated.service -> decorated.service');

        (new Container($builder))->get('decorated.service');
    }

    public function testCallableDecoratorCannotResolveItselfThroughAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('decorated.service', ReentrantResolutionBaseService::class);
        $builder->alias('decorated.alias', 'decorated.service');
        $builder->decorate(
            'decorated.service',
            static fn(ContainerInterface $container, mixed $inner): mixed => $container->get('decorated.alias'),
        );

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: decorated.service -> decorated.service');

        (new Container($builder))->get('decorated.alias');
    }

    public function testFactoryDecoratorAliasAndSingletonCacheContinueToResolveNormally(): void
    {
        $factoryCalls = 0;
        $decoratorCalls = 0;
        $builder = new ContainerBuilder();
        $builder->singleton(
            'service',
            static function (ContainerInterface $container) use (&$factoryCalls): ReentrantResolutionBaseService {
                $factoryCalls++;

                $dependency = $container->get('dependency');
                assert($dependency instanceof ReentrantResolutionBaseService);

                return $dependency;
            },
        );
        $builder->singleton('dependency', ReentrantResolutionBaseService::class);
        $builder->alias('service.alias', 'service');
        $builder->decorate(
            'service',
            static function (ContainerInterface $container, mixed $inner) use (&$decoratorCalls): ReentrantResolutionDecoratedService {
                unset($container);
                $decoratorCalls++;

                return new ReentrantResolutionDecoratedService($inner);
            },
        );
        $container = new Container($builder);

        $first = $container->get('service.alias');
        $second = $container->get('service');

        self::assertInstanceOf(ReentrantResolutionDecoratedService::class, $first);
        self::assertSame($first, $second);
        self::assertSame(1, $factoryCalls);
        self::assertSame(1, $decoratorCalls);
    }
}

final class ReentrantResolutionBaseService {}

final class ReentrantResolutionDecoratedService
{
    public function __construct(public mixed $inner) {}
}
