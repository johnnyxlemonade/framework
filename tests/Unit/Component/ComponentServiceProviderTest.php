<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbFactory;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbRenderer;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbServiceProvider;
use Lemonade\Framework\Component\Breadcrumb\Config\BreadcrumbsConfigDefinition;
use Lemonade\Framework\Component\ComponentRegistry;
use Lemonade\Framework\Component\ComponentServiceProvider;
use Lemonade\Framework\Component\Config\ComponentConfigDefinition;
use Lemonade\Framework\Component\Config\ComponentConfigResolver;
use Lemonade\Framework\Component\Meta\Config\MetaConfigDefinition;
use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Meta\MetaServiceProvider;
use Lemonade\Framework\Component\Pagination\Config\PaginationConfigDefinition;
use Lemonade\Framework\Component\Pagination\PaginationComponent;
use Lemonade\Framework\Component\Pagination\PaginationFactory;
use Lemonade\Framework\Component\Pagination\PaginationRenderer;
use Lemonade\Framework\Component\Pagination\PaginationServiceProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Localization\TranslatorInterface;
use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ComponentServiceProviderTest extends TestCase
{
    public function testRegisterBindsComponentRegistryWithBuiltInComponents(): void
    {
        $container = $this->buildContainer();
        $provider = new ComponentServiceProvider();

        $provider->register($container);

        self::assertTrue($container->isBound(ComponentRegistry::class));

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        self::assertTrue($registry->has('breadcrumb'));
        self::assertTrue($registry->has('pagination'));
        self::assertTrue($registry->has('meta'));

        self::assertSame([
            'breadcrumb' => BreadcrumbComponent::class,
            'pagination' => PaginationComponent::class,
            'meta' => MetaComponent::class,
        ], $registry->all());
    }

    public function testRegistryResolvesBuiltInComponentObjects(): void
    {
        $container = $this->buildContainer();
        $provider = new ComponentServiceProvider();
        $provider->register($container);

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        self::assertInstanceOf(BreadcrumbComponent::class, $registry->get('breadcrumb'));
        self::assertInstanceOf(PaginationComponent::class, $registry->get('pagination'));
        self::assertInstanceOf(MetaComponent::class, $registry->get('meta'));
    }

    public function testConvenienceMethodsResolveBuiltInComponents(): void
    {
        $container = $this->buildContainer();
        $provider = new ComponentServiceProvider();
        $provider->register($container);

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        self::assertInstanceOf(BreadcrumbComponent::class, $registry->breadcrumb());
        self::assertInstanceOf(PaginationComponent::class, $registry->pagination());
        self::assertInstanceOf(MetaComponent::class, $registry->meta());
    }

    public function testRegistryRegistersComponentFromConfig(): void
    {
        $container = $this->buildContainer(
            ComponentConfigDefinition::create()->component('navigation', TestNavigationComponent::class),
        );
        $provider = new ComponentServiceProvider();
        $provider->register($container);

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        self::assertTrue($registry->has('navigation'));
        self::assertSame(TestNavigationComponent::class, $registry->all()['navigation']);
        self::assertInstanceOf(TestNavigationComponent::class, $registry->get('navigation'));
    }

    public function testGetWithExpectedClassReturnsTypedNavigationComponent(): void
    {
        $container = $this->buildContainer(
            ComponentConfigDefinition::create()->component('navigation', TestNavigationComponent::class),
        );
        $provider = new ComponentServiceProvider();
        $provider->register($container);

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        $navigation = $registry->get('navigation', TestNavigationComponent::class);

        self::assertInstanceOf(TestNavigationComponent::class, $navigation);
    }

    public function testGetWithoutExpectedClassRemainsBackwardCompatibleAndReturnsObject(): void
    {
        $container = $this->buildContainer(
            ComponentConfigDefinition::create()->component('navigation', TestNavigationComponent::class),
        );
        $provider = new ComponentServiceProvider();
        $provider->register($container);

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        $navigation = $registry->get('navigation');

        self::assertIsObject($navigation);
    }

    public function testGetThrowsClearExceptionForExpectedClassMismatch(): void
    {
        $container = $this->buildContainer(
            ComponentConfigDefinition::create()->component('navigation', TestNavigationComponent::class),
        );
        $provider = new ComponentServiceProvider();
        $provider->register($container);

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Component [navigation] must be instance of');

        $registry->get('navigation', WrongComponent::class);
    }

    public function testResolverRejectsEmptyComponentName(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must use non-empty string keys');

        (new ComponentConfigResolver())->resolve(
            ComponentConfigDefinition::create()->component('', TestNavigationComponent::class),
        );
    }

    public function testResolverRejectsNonExistingComponentClass(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('references non-existing class');

        $definition = ComponentConfigDefinition::create()->component('slider', TestNavigationComponent::class);
        $this->overrideComponentDefinitionMap($definition, ['slider' => 'App\\Component\\MissingSliderComponent']);

        (new ComponentConfigResolver())->resolve(
            $definition,
        );
    }

    public function testResolverRejectsEmptyComponentClass(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be a non-empty class-string');

        $definition = ComponentConfigDefinition::create()->component('navigation', TestNavigationComponent::class);
        $this->overrideComponentDefinitionMap($definition, ['navigation' => '']);

        (new ComponentConfigResolver())->resolve(
            $definition,
        );
    }

    public function testGetThrowsClearExceptionForUnknownComponent(): void
    {
        $container = $this->buildContainer();
        $provider = new ComponentServiceProvider();
        $provider->register($container);

        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Component [unknown] is not registered.');

        $registry->get('unknown');
    }

    public function testBreadcrumbServiceProviderRegistersServices(): void
    {
        $container = $this->buildContainer();
        $provider = new BreadcrumbServiceProvider();

        $provider->register($container);

        self::assertTrue($container->isBound(BreadcrumbFactory::class));
        self::assertTrue($container->isBound(BreadcrumbRenderer::class));
        self::assertTrue($container->isBound(BreadcrumbComponent::class));
    }

    public function testPaginationServiceProviderRegistersServices(): void
    {
        $container = $this->buildContainer();
        $provider = new PaginationServiceProvider();

        $provider->register($container);

        self::assertTrue($container->isBound(PaginationFactory::class));
        self::assertTrue($container->isBound(PaginationRenderer::class));
        self::assertTrue($container->isBound(PaginationComponent::class));
    }

    public function testMetaServiceProviderRegistersServices(): void
    {
        $container = $this->buildContainer();
        $provider = new MetaServiceProvider();

        $provider->register($container);

        self::assertTrue($container->isBound(MetaComponent::class));
    }

    private function buildContainer(?ComponentConfigDefinition $components = null): Container
    {
        $container = new Container();
        $registry = new ConfigDefinitionRegistry();
        $registry->addDefinition(
            PaginationConfigDefinition::create()
                ->defaultPerPage(20)
                ->maxPerPage(100)
                ->visiblePages(7)
                ->showFirstLast(),
        );
        $registry->addDefinition(
            BreadcrumbsConfigDefinition::create()
                ->frontendRoot('Domu', '/')
                ->adminRoot('Admin', '/admin'),
        );
        $registry->addDefinition(
            MetaConfigDefinition::create()
                ->websiteName('website')
                ->charset('UTF-8')
                ->viewport('width=device-width, initial-scale=1')
                ->rating('General')
                ->titleSeparator(' - '),
        );
        if ($components instanceof ComponentConfigDefinition) {
            $registry->addDefinition($components);
        }
        $container->singleton(ConfigDefinitionRegistry::class, $registry);
        $container->singleton(ServerRequestInterface::class, new ServerRequest('GET', '/'));
        $container->singleton(TranslatorInterface::class, new TestTranslator());

        return $container;
    }

    /**
     * @param array<mixed> $components
     */
    private function overrideComponentDefinitionMap(ComponentConfigDefinition $definition, array $components): void
    {
        $property = new \ReflectionProperty($definition, 'components');
        $property->setAccessible(true);
        $property->setValue($definition, $components);
    }
}

final class TestNavigationComponent {}
final class WrongComponent {}

final class TestTranslator implements TranslatorInterface
{
    public function setLocale(?string $locale): TranslatorInterface
    {
        unset($locale);

        return $this;
    }

    public function locale(): ?string
    {
        return null;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        unset($replacements, $locale);

        return $key;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($group, $locale);

        return [];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return [];
    }
}
