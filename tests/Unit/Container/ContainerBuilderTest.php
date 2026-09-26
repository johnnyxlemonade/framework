<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\Definition\ClassTarget;
use Lemonade\Framework\Container\Definition\FactoryTarget;
use Lemonade\Framework\Container\Definition\InstanceTarget;
use Lemonade\Framework\Container\ServiceLifetime;
use PHPUnit\Framework\TestCase;

final class ContainerBuilderTest extends TestCase
{
    public function testCompilePreservesDefinitionTargetsAndLifetimes(): void
    {
        $builder = new ContainerBuilder();
        $instance = new \stdClass();
        $builder->transient('transient', ContainerBuilderTestService::class);
        $builder->singleton('singleton', ContainerBuilderTestService::class);
        $builder->set('factory', static fn(): \stdClass => new \stdClass());
        $builder->instance('instance', $instance);

        $plan = $builder->compile();

        self::assertInstanceOf(ClassTarget::class, $plan->definition('transient')?->target);
        self::assertSame(ServiceLifetime::Transient, $plan->definition('transient')?->lifetime);
        self::assertInstanceOf(ClassTarget::class, $plan->definition('singleton')?->target);
        self::assertSame(ServiceLifetime::Singleton, $plan->definition('singleton')?->lifetime);
        self::assertInstanceOf(FactoryTarget::class, $plan->definition('factory')?->target);
        self::assertInstanceOf(InstanceTarget::class, $plan->definition('instance')?->target);
        self::assertSame(ServiceLifetime::Singleton, $plan->definition('instance')?->lifetime);
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
