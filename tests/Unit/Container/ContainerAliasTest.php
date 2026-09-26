<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\Exception\AliasCycleException;
use Lemonade\Framework\Container\Exception\AliasTargetNotFoundException;
use Lemonade\Framework\Container\Exception\DuplicateServiceAliasException;
use Lemonade\Framework\Container\Exception\InvalidServiceAliasException;
use PHPUnit\Framework\TestCase;

final class ContainerAliasTest extends TestCase
{
    public function testAliasResolvesThroughGetAndIsReportedByHas(): void
    {
        $container = $this->containerWithAlias('service.alias', 'service.canonical');

        self::assertTrue($container->has('service.alias'));
        self::assertInstanceOf(ContainerAliasService::class, $container->get('service.alias'));
    }

    public function testSingletonAliasReturnsCanonicalInstance(): void
    {
        $container = $this->containerWithAlias('service.alias', 'service.canonical');

        self::assertSame(
            $container->get('service.canonical'),
            $container->get('service.alias'),
        );
    }

    public function testConcreteContainerExposesBuilderAliasBridge(): void
    {
        $container = new Container();
        $container->singleton('service.canonical', ContainerAliasService::class);
        $container->alias('service.alias', 'service.canonical');

        self::assertSame($container->get('service.canonical'), $container->get('service.alias'));
    }

    public function testTransientAliasUsesCanonicalTransientDefinition(): void
    {
        $builder = new ContainerBuilder();
        $builder->transient('service.canonical', ContainerAliasService::class);
        $builder->alias('service.alias', 'service.canonical');
        $container = new Container($builder);

        self::assertNotSame($container->get('service.alias'), $container->get('service.canonical'));
    }

    public function testAliasChainCanonicalizesToFinalServiceId(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('service.canonical', ContainerAliasService::class);
        $builder->alias('service.a', 'service.b');
        $builder->alias('service.b', 'service.canonical');
        $container = new Container($builder);

        self::assertSame('service.canonical', $container->compile()->canonicalId('service.a'));
        self::assertSame($container->get('service.a'), $container->get('service.canonical'));
    }

    public function testAliasCycleFailsWithFullPath(): void
    {
        $builder = new ContainerBuilder();
        $builder->alias('service.a', 'service.b');
        $builder->alias('service.b', 'service.a');

        $this->expectException(AliasCycleException::class);
        $this->expectExceptionMessage('service.a -> service.b -> service.a');
        $builder->compile();
    }

    public function testMissingAliasTargetFailsWithClearException(): void
    {
        $builder = new ContainerBuilder();
        $builder->alias('service.alias', 'service.missing');
        $container = new Container($builder);

        self::assertFalse($container->has('service.alias'));
        $this->expectException(AliasTargetNotFoundException::class);
        $this->expectExceptionMessage('service.alias');
        $container->get('service.alias');
    }

    public function testAliasCannotCollideWithServiceDefinition(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('service.canonical', ContainerAliasService::class);

        $this->expectException(DuplicateServiceAliasException::class);
        $builder->alias('service.canonical', 'service.target');
    }

    public function testDuplicateAliasFails(): void
    {
        $builder = new ContainerBuilder();
        $builder->alias('service.alias', 'service.first');

        $this->expectException(DuplicateServiceAliasException::class);
        $builder->alias('service.alias', 'service.second');
    }

    public function testAliasCannotTargetItself(): void
    {
        $this->expectException(InvalidServiceAliasException::class);
        (new ContainerBuilder())->alias('service.alias', 'service.alias');
    }

    public function testLegacyContainerBindingsContinueToWorkWithoutAliases(): void
    {
        $container = new Container();
        $container->singleton('service.canonical', ContainerAliasService::class);

        self::assertSame($container->get('service.canonical'), $container->get('service.canonical'));
    }

    /**
     * @param non-empty-string $alias
     * @param non-empty-string $target
     */
    private function containerWithAlias(string $alias, string $target): Container
    {
        $builder = new ContainerBuilder();
        $builder->singleton('service.canonical', ContainerAliasService::class);
        $builder->alias($alias, $target);

        return new Container($builder);
    }
}

final class ContainerAliasService {}
