<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\InvalidContextualBindingException;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;

final class ContextualBindingTest extends TestCase
{
    public function testContextualInterfaceBindingAppliesOnlyToItsConsumer(): void
    {
        $builder = new ContainerBuilder();
        $builder->when(ContextualClockConsumer::class)->needs(ContextualClock::class)->give(ContextualFrozenClock::class);
        $container = new Container($builder);

        self::assertInstanceOf(ContextualFrozenClock::class, $container->get(ContextualClockConsumer::class)->clock);

        $this->expectException(ServiceNotFoundException::class);
        $container->get(ContextualOtherClockConsumer::class);
    }

    public function testContextualBindingTakesPrecedenceOverGlobalBinding(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton(ContextualClock::class, ContextualSystemClock::class);
        $builder->when(ContextualClockConsumer::class)->needs(ContextualClock::class)->give(ContextualFrozenClock::class);
        $container = new Container($builder);

        self::assertInstanceOf(ContextualFrozenClock::class, $container->get(ContextualClockConsumer::class)->clock);
        self::assertInstanceOf(ContextualSystemClock::class, $container->get(ContextualClock::class));
    }

    public function testContextualBindingCanOverrideConcreteDependency(): void
    {
        $builder = new ContainerBuilder();
        $builder->when(ContextualConcreteConsumer::class)->needs(ContextualConcreteDependency::class)->give(ContextualAlternativeDependency::class);

        self::assertInstanceOf(
            ContextualAlternativeDependency::class,
            (new Container($builder))->get(ContextualConcreteConsumer::class)->dependency,
        );
    }

    public function testContextualFactoryCanProvideDependencyValue(): void
    {
        $builder = new ContainerBuilder();
        $builder->when(ContextualClockConsumer::class)->needs(ContextualClock::class)->give(
            static fn(ContainerInterface $container): ContextualClock => new ContextualFrozenClock(),
        );

        self::assertInstanceOf(ContextualFrozenClock::class, (new Container($builder))->get(ContextualClockConsumer::class)->clock);
    }

    public function testContextualInvokableObjectIsAnExplicitValueRatherThanAFactory(): void
    {
        $value = new ContextualInvokableClock();
        $builder = new ContainerBuilder();
        $builder->when(ContextualClockConsumer::class)->needs(ContextualClock::class)->give($value);

        self::assertSame($value, (new Container($builder))->get(ContextualClockConsumer::class)->clock);
        self::assertSame(0, $value->invocations);
    }

    public function testContextualParameterSuppliesExplicitScalar(): void
    {
        $builder = new ContainerBuilder();
        $builder->when(ContextualCsvConsumer::class)->parameter('delimiter')->value(';');

        self::assertSame(';', (new Container($builder))->get(ContextualCsvConsumer::class)->delimiter);
    }

    public function testDefaultParameterValueIsUsedWithoutContextualBinding(): void
    {
        self::assertSame(',', (new Container())->get(ContextualDefaultDelimiterConsumer::class)->delimiter);
    }

    public function testScalarWithoutContextualBindingOrDefaultFails(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('no resolvable class type');
        (new Container())->get(ContextualCsvConsumer::class);
    }

    public function testConfigParameterResolvesExplicitTypedConfigService(): void
    {
        $config = new ContextualStorageConfig('bucket-a');
        $builder = new ContainerBuilder();
        $builder->instance(ContextualStorageConfig::class, $config);
        $builder->when(ContextualStorageConsumer::class)->parameter('config')->config(ContextualStorageConfig::class);

        self::assertSame($config, (new Container($builder))->get(ContextualStorageConsumer::class)->config);
    }

    public function testParameterBindingForMissingConstructorParameterFailsClearly(): void
    {
        $this->expectException(InvalidContextualBindingException::class);
        $this->expectExceptionMessage('does not match a constructor parameter');

        (new ContainerBuilder())->when(ContextualCsvConsumer::class)->parameter('unknown')->value(',');
    }

    public function testMissingContextualConsumerFailsClearly(): void
    {
        $this->expectException(InvalidContextualBindingException::class);
        $this->expectExceptionMessage('does not exist');

        (new ContainerBuilder())->when('Missing\\ContextualConsumer');
    }

    public function testCycleDetectionIncludesContextualBindingResolution(): void
    {
        $builder = new ContainerBuilder();
        $builder->when(ContextualCycleFirst::class)->needs(ContextualCycleContract::class)->give(ContextualCycleSecond::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(ContextualCycleFirst::class . ' -> ' . ContextualCycleSecond::class . ' -> ' . ContextualCycleFirst::class);
        (new Container($builder))->get(ContextualCycleFirst::class);
    }

    public function testAliasesAndDecoratorsContinueToWorkWithContextualBindings(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton(ContextualClock::class, ContextualSystemClock::class);
        $builder->alias('clock.alias', ContextualClock::class);
        $builder->decorate('clock.alias', static function (ContainerInterface $container, mixed $inner): ContextualDecoratedClock {
            unset($container);

            return new ContextualDecoratedClock($inner);
        });
        $builder->when(ContextualClockConsumer::class)->needs(ContextualClock::class)->give('clock.alias');
        $container = new Container($builder);

        self::assertInstanceOf(ContextualDecoratedClock::class, $container->get(ContextualClockConsumer::class)->clock);
        self::assertSame($container->get(ContextualClock::class), $container->get('clock.alias'));
    }

    public function testLegacySingletonBindingRemainsCompatible(): void
    {
        $container = new Container();
        $container->singleton('service', ContextualConcreteDependency::class);

        self::assertSame($container->get('service'), $container->get('service'));
    }
}

interface ContextualClock {}

final class ContextualSystemClock implements ContextualClock {}

final class ContextualFrozenClock implements ContextualClock {}

final class ContextualInvokableClock implements ContextualClock
{
    public int $invocations = 0;

    public function __invoke(ContainerInterface $container): ContextualClock
    {
        unset($container);
        $this->invocations++;

        return new ContextualFrozenClock();
    }
}

final class ContextualDecoratedClock implements ContextualClock
{
    public function __construct(public mixed $inner) {}
}

final class ContextualClockConsumer
{
    public function __construct(public ContextualClock $clock) {}
}

final class ContextualOtherClockConsumer
{
    public function __construct(public ContextualClock $clock) {}
}

class ContextualConcreteDependency {}

final class ContextualAlternativeDependency extends ContextualConcreteDependency {}

final class ContextualConcreteConsumer
{
    public function __construct(public ContextualConcreteDependency $dependency) {}
}

final class ContextualCsvConsumer
{
    public function __construct(public string $delimiter) {}
}

final class ContextualDefaultDelimiterConsumer
{
    public function __construct(public string $delimiter = ',') {}
}

final class ContextualStorageConfig
{
    public function __construct(public string $bucket) {}
}

final class ContextualStorageConsumer
{
    public function __construct(public ContextualStorageConfig $config) {}
}

interface ContextualCycleContract {}

final class ContextualCycleFirst
{
    public function __construct(public ContextualCycleContract $second) {}
}

final class ContextualCycleSecond implements ContextualCycleContract
{
    public function __construct(public ContextualCycleFirst $first) {}
}
