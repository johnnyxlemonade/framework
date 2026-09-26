<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\DecorationTargetNotFoundException;
use Lemonade\Framework\Container\ServiceDecoratorInterface;
use PHPUnit\Framework\TestCase;

final class ContainerDecorationTest extends TestCase
{
    public function testCallableDecoratorWrapsSingletonAndCachesFullChain(): void
    {
        $calls = 0;
        $builder = new ContainerBuilder();
        $builder->singleton('service', DecorationBaseService::class);
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$calls): DecorationWrappedService {
            unset($container);
            $calls++;

            return new DecorationWrappedService($inner, 'singleton');
        });
        $container = new Container($builder);

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertInstanceOf(DecorationWrappedService::class, $first);
        self::assertSame($first, $second);
        self::assertSame(1, $calls);
    }

    public function testCallableDecoratorRebuildsTransientChainOnEveryGet(): void
    {
        $calls = 0;
        $builder = new ContainerBuilder();
        $builder->transient('service', DecorationBaseService::class);
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$calls): DecorationWrappedService {
            unset($container);
            $calls++;

            return new DecorationWrappedService($inner, 'transient');
        });
        $container = new Container($builder);

        self::assertNotSame($container->get('service'), $container->get('service'));
        self::assertSame(2, $calls);
    }

    public function testDecoratorsApplyByDescendingPriority(): void
    {
        $events = [];
        $builder = new ContainerBuilder();
        $builder->singleton('service', DecorationBaseService::class);
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$events): mixed {
            unset($container, $inner);
            $events[] = 'low';

            return new \stdClass();
        }, priority: 1);
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$events): mixed {
            unset($container, $inner);
            $events[] = 'high';

            return new \stdClass();
        }, priority: 10);

        (new Container($builder))->get('service');

        self::assertSame(['high', 'low'], $events);
    }

    public function testDecoratorsWithSamePriorityKeepRegistrationOrder(): void
    {
        $events = [];
        $builder = new ContainerBuilder();
        $builder->singleton('service', DecorationBaseService::class);
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$events): mixed {
            unset($container, $inner);
            $events[] = 'first';

            return new \stdClass();
        });
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$events): mixed {
            unset($container, $inner);
            $events[] = 'second';

            return new \stdClass();
        });

        (new Container($builder))->get('service');

        self::assertSame(['first', 'second'], $events);
    }

    public function testDecorationThroughAliasBindsToCanonicalService(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('service.canonical', DecorationBaseService::class);
        $builder->alias('service.alias', 'service.canonical');
        $builder->decorate('service.alias', DecorationClassDecorator::class);
        $container = new Container($builder);

        $canonical = $container->get('service.canonical');

        self::assertInstanceOf(DecorationClassWrappedService::class, $canonical);
        self::assertSame($canonical, $container->get('service.alias'));
    }

    public function testDecorationTargetMustExist(): void
    {
        $this->expectException(DecorationTargetNotFoundException::class);
        $this->expectExceptionMessage('service.missing');

        (new ContainerBuilder())->decorate('service.missing', static fn(ContainerInterface $container, mixed $inner): mixed => $inner);
    }

    public function testDecorationChainDoesNotResolveTheSameServiceAgain(): void
    {
        $calls = 0;
        $builder = new ContainerBuilder();
        $builder->singleton('service', DecorationBaseService::class);
        $builder->decorate('service', static function (ContainerInterface $container, mixed $inner) use (&$calls): mixed {
            unset($container);
            $calls++;

            return new DecorationWrappedService($inner, 'once');
        });

        $resolved = (new Container($builder))->get('service');

        self::assertInstanceOf(DecorationWrappedService::class, $resolved);
        self::assertSame(1, $calls);
    }

    public function testTransientAliasResolvesDecoratedTransient(): void
    {
        $builder = new ContainerBuilder();
        $builder->transient('service.canonical', DecorationBaseService::class);
        $builder->alias('service.alias', 'service.canonical');
        $builder->decorate('service.alias', DecorationClassDecorator::class);
        $container = new Container($builder);

        self::assertNotSame($container->get('service.alias'), $container->get('service.canonical'));
    }

    public function testDecoratedInstanceTargetCachesTheDecoratedResult(): void
    {
        $builder = new ContainerBuilder();
        $builder->instance('service', new DecorationBaseService());
        $builder->decorate('service', DecorationClassDecorator::class);
        $container = new Container($builder);

        self::assertSame($container->get('service'), $container->get('service'));
    }

    public function testLegacyBindingsRemainUndecoratedWithoutExplicitDecoration(): void
    {
        $container = new Container();
        $container->singleton('service', DecorationBaseService::class);

        self::assertInstanceOf(DecorationBaseService::class, $container->get('service'));
    }
}

final class DecorationBaseService {}

final class DecorationWrappedService
{
    public function __construct(
        public mixed $inner,
        public string $name,
    ) {}
}

final class DecorationClassWrappedService
{
    public function __construct(public mixed $inner) {}
}

final class DecorationClassDecorator implements ServiceDecoratorInterface
{
    public function decorate(mixed $inner): mixed
    {
        return new DecorationClassWrappedService($inner);
    }
}
