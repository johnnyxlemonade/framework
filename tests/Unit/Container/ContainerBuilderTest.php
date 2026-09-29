<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\ContainerFrozenException;
use Lemonade\Framework\Container\TaggedServicesInterface;
use Lemonade\Framework\Container\Definition\ClassTarget;
use Lemonade\Framework\Container\Definition\FactoryTarget;
use Lemonade\Framework\Container\Definition\InstanceTarget;
use Lemonade\Framework\Container\ServiceLifetime;
use PHPUnit\Framework\TestCase;

final class ContainerBuilderTest extends TestCase
{
    public function testScopedRegistrationIsBuilderOnly(): void
    {
        $methods = array_values(array_unique(get_class_methods(ContainerInterface::class)));
        sort($methods);

        self::assertSame(['get', 'has'], $methods);
        self::assertContains('scoped', get_class_methods(ContainerBuilderInterface::class));
    }

    public function testFreezePreservesRuntimeResolutionAndRejectsAllDefinitionMutations(): void
    {
        $container = new Container();
        $container->singleton('tagged', ContainerBuilderTestService::class);
        $container->tag('tagged', 'extension');
        $plan = $container->freeze();

        self::assertTrue($container->isFrozen());
        self::assertSame($plan, $container->compile());
        self::assertTrue($container->has('tagged'));
        self::assertInstanceOf(ContainerBuilderTestService::class, $container->get('tagged'));
        self::assertInstanceOf(TaggedServicesInterface::class, $container);
        self::assertCount(1, iterator_to_array($container->tagged('extension')));

        foreach ([
            static fn() => $container->set('another', ContainerBuilderTestService::class),
            static fn() => $container->singleton('another', ContainerBuilderTestService::class),
            static fn() => $container->scoped('another', ContainerBuilderTestService::class),
            static fn() => $container->instance('another', new \stdClass()),
            static fn() => $container->alias('another.alias', 'tagged'),
            static fn() => $container->decorate('tagged', static fn($runtime, $service) => $service),
            static fn() => $container->when(ContainerBuilderTestService::class),
            static fn() => $container->tag('tagged', 'another-extension'),
        ] as $mutate) {
            try {
                $mutate();
                self::fail('Frozen container accepted a definition mutation.');
            } catch (ContainerFrozenException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCompilePreservesDefinitionTargetsAndLifetimes(): void
    {
        $builder = new ContainerBuilder();
        $instance = new \stdClass();
        $builder->transient('transient', ContainerBuilderTestService::class);
        $builder->singleton('singleton', ContainerBuilderTestService::class);
        $builder->set('factory', static fn(): \stdClass => new \stdClass());
        $builder->instance('instance', $instance);

        $plan = $builder->compile();
        $transient = $plan->definition('transient');
        $singleton = $plan->definition('singleton');
        $factory = $plan->definition('factory');
        $instanceDefinition = $plan->definition('instance');

        self::assertNotNull($transient);
        self::assertNotNull($singleton);
        self::assertNotNull($factory);
        self::assertNotNull($instanceDefinition);

        self::assertInstanceOf(ClassTarget::class, $transient->target);
        self::assertSame(ServiceLifetime::Transient, $transient->lifetime);
        self::assertInstanceOf(ClassTarget::class, $singleton->target);
        self::assertSame(ServiceLifetime::Singleton, $singleton->lifetime);
        self::assertInstanceOf(FactoryTarget::class, $factory->target);
        self::assertInstanceOf(InstanceTarget::class, $instanceDefinition->target);
        self::assertSame(ServiceLifetime::Singleton, $instanceDefinition->lifetime);
    }

    public function testCompiledPlanPreservesTagDeclarationOrder(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('first', ContainerBuilderTestService::class);
        $builder->singleton('second', ContainerBuilderTestService::class);
        $builder->tag('first', 'extension');
        $builder->tag('second', 'extension');

        self::assertSame(['first', 'second'], $builder->compile()->taggedServiceIds('extension'));
    }

    public function testContainerResolvesDefinitionsFromInjectedBuilder(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('service', ContainerBuilderTestService::class);
        $container = new Container($builder);

        self::assertSame($container->get('service'), $container->get('service'));
    }
}

final class ContainerBuilderTestService {}
