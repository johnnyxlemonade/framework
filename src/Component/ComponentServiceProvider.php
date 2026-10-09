<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbServiceProvider;
use Lemonade\Framework\Component\Config\ComponentConfig;
use Lemonade\Framework\Component\Config\ComponentConfigDefinition;
use Lemonade\Framework\Component\Config\ComponentConfigResolver;
use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Meta\MetaServiceProvider;
use Lemonade\Framework\Component\Pagination\PaginationComponent;
use Lemonade\Framework\Component\Pagination\PaginationServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registers built-in components, their configuration and the view-facing component registry.
 *
 * Configured component names may replace the built-in mapping during lazy registry resolution.
 */
final class ComponentServiceProvider implements ServiceProviderInterface
{
    /**
     * Defines the default component-name to service-class mappings.
     *
     * @var array<string, class-string>
     */
    private const array COMPONENTS = [
        'breadcrumb' => BreadcrumbComponent::class,
        'pagination' => PaginationComponent::class,
        'meta' => MetaComponent::class,
    ];

    /**
     * Registers component services, resolved configuration and the lazy registry factory.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ComponentConfigResolver::class, ComponentConfigResolver::class);
        $container->singleton(ComponentConfig::class, static function (ContainerInterface $container): ComponentConfig {
            return $container
                ->get(ComponentConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ComponentConfigDefinition::moduleKey(),
                    ComponentConfigDefinition::class,
                ));
        });

        $this->registerBreadcrumb($container);
        $this->registerPagination($container);
        $this->registerMeta($container);
        $this->registerRegistry($container);
    }

    private function registerBreadcrumb(ContainerBuilderInterface $container): void
    {
        (new BreadcrumbServiceProvider())->register($container);
    }

    private function registerPagination(ContainerBuilderInterface $container): void
    {
        (new PaginationServiceProvider())->register($container);
    }

    private function registerMeta(ContainerBuilderInterface $container): void
    {
        (new MetaServiceProvider())->register($container);
    }

    private function registerRegistry(ContainerBuilderInterface $container): void
    {
        $container->set(ComponentRegistry::class, function (ContainerInterface $container): ComponentRegistry {
            $registry = new ComponentRegistry($container);

            foreach (self::COMPONENTS as $name => $componentClass) {
                $registry->register($name, $componentClass);
            }

            foreach ($container->get(ComponentConfig::class)->components as $name => $componentClass) {
                $registry->register($name, $componentClass);
            }

            return $registry;
        });
    }
}
