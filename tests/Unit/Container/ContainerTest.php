<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;

interface TestContractInterface
{
    public function id(): string;
}

final class TestContractImplementation implements TestContractInterface
{
    public function id(): string
    {
        return 'impl';
    }
}

final class NeedsInterfaceWithoutBinding
{
    public function __construct(
        public readonly TestContractInterface $dependency,
    ) {}
}

final class NeedsInterfaceWithBinding
{
    public function __construct(
        public readonly TestContractInterface $dependency,
    ) {}
}

final class PlainConcreteClass
{
    public function id(): string
    {
        return 'plain';
    }
}

abstract class NonInstantiableClass
{
}

final class DependencyClass
{
    public function value(): string
    {
        return 'dep';
    }
}

final class NeedsConcreteDependency
{
    public function __construct(
        public readonly DependencyClass $dependency,
    ) {}
}

final class ContainerTest extends TestCase
{
    public function testIsBoundReturnsTrueOnlyForExplicitBinding(): void
    {
        $container = new Container();
        $container->singleton('service.id', new \stdClass());

        self::assertTrue($container->isBound('service.id'));
        self::assertFalse($container->isBound(PlainConcreteClass::class));
    }

    public function testHasReturnsTrueForExplicitBinding(): void
    {
        $container = new Container();
        $container->singleton('service.id', new \stdClass());

        self::assertTrue($container->has('service.id'));
    }

    public function testHasReturnsTrueForExistingConcreteClassViaAutowiring(): void
    {
        $container = new Container();

        self::assertTrue($container->has(PlainConcreteClass::class));
    }

    public function testIsBoundReturnsFalseForExistingButUnboundConcreteClass(): void
    {
        $container = new Container();

        self::assertFalse($container->isBound(PlainConcreteClass::class));
    }

    public function testInterfaceDependencyWithoutBindingThrows(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $container->get(NeedsInterfaceWithoutBinding::class);
    }

    public function testInterfaceDependencyWithBindingResolves(): void
    {
        $container = new Container();
        $container->singleton(TestContractInterface::class, TestContractImplementation::class);

        $resolved = $container->get(NeedsInterfaceWithBinding::class);

        self::assertInstanceOf(NeedsInterfaceWithBinding::class, $resolved);
        self::assertInstanceOf(TestContractImplementation::class, $resolved->dependency);
    }

    public function testSetReturnsNewInstanceOnEachGetWhenFactoryCreatesNewObject(): void
    {
        $container = new Container();
        $container->set('factory.service', static fn(): \stdClass => new \stdClass());

        $first = $container->get('factory.service');
        $second = $container->get('factory.service');

        self::assertNotSame($first, $second);
    }

    public function testSingletonReturnsSameInstanceOnRepeatedGet(): void
    {
        $container = new Container();
        $container->singleton('singleton.service', static fn(): \stdClass => new \stdClass());

        $first = $container->get('singleton.service');
        $second = $container->get('singleton.service');

        self::assertSame($first, $second);
    }

    public function testSingletonWithObjectInstanceReturnsSameObject(): void
    {
        $container = new Container();
        $instance = new \stdClass();
        $container->singleton('singleton.object', $instance);

        self::assertSame($instance, $container->get('singleton.object'));
        self::assertSame($instance, $container->get('singleton.object'));
    }

    public function testSetAfterExistingSingletonInvalidatesStoredInstance(): void
    {
        $container = new Container();
        $container->singleton('service', static fn(): \stdClass => new \stdClass());

        $first = $container->get('service');
        $container->set('service', static fn(): \stdClass => new \stdClass());
        $second = $container->get('service');

        self::assertNotSame($first, $second);
    }

    public function testGetForMissingServiceThrowsServiceNotFoundException(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $container->get('missing.service');
    }

    public function testGetForNonInstantiableClassThrowsContainerException(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $container->get(NonInstantiableClass::class);
    }

    public function testConcreteConstructorDependencyIsAutowired(): void
    {
        $container = new Container();

        $resolved = $container->get(NeedsConcreteDependency::class);

        self::assertInstanceOf(NeedsConcreteDependency::class, $resolved);
        self::assertInstanceOf(DependencyClass::class, $resolved->dependency);
        self::assertSame('dep', $resolved->dependency->value());
    }
}
