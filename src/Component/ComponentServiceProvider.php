<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbServiceProvider;
use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Meta\MetaServiceProvider;
use Lemonade\Framework\Component\Pagination\PaginationComponent;
use Lemonade\Framework\Component\Pagination\PaginationServiceProvider;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use LogicException;

final class ComponentServiceProvider implements ServiceProviderInterface
{
    /**
     * @var array<string, class-string>
     */
    private const COMPONENTS = [
        'breadcrumb' => BreadcrumbComponent::class,
        'pagination' => PaginationComponent::class,
        'meta' => MetaComponent::class,
    ];

    public function register(ContainerInterface $container): void
    {
        $this->registerBreadcrumb($container);
        $this->registerPagination($container);
        $this->registerMeta($container);
        $this->registerRegistry($container);
    }

    private function registerBreadcrumb(ContainerInterface $container): void
    {
        (new BreadcrumbServiceProvider())->register($container);
    }

    private function registerPagination(ContainerInterface $container): void
    {
        (new PaginationServiceProvider())->register($container);
    }

    private function registerMeta(ContainerInterface $container): void
    {
        (new MetaServiceProvider())->register($container);
    }

    private function registerRegistry(ContainerInterface $container): void
    {
        $container->singleton(ComponentRegistry::class, function (ContainerInterface $container): ComponentRegistry {
            $registry = new ComponentRegistry($container);

            foreach (self::COMPONENTS as $name => $componentClass) {
                $registry->register($name, $componentClass);
            }

            foreach ($this->resolveCustomComponents($container) as $name => $componentClass) {
                $registry->register($name, $componentClass);
            }

            return $registry;
        });
    }

    /**
     * @return array<string, class-string>
     */
    private function resolveCustomComponents(ContainerInterface $container): array
    {
        $config = $container->get(Config::class);

        if (!$config instanceof Config) {
            throw new LogicException(sprintf(
                'Service [%s] must resolve to %s, %s given.',
                Config::class,
                Config::class,
                get_debug_type($config),
            ));
        }

        $components = $config->get('components', []);

        if (!is_array($components)) {
            throw new LogicException(sprintf(
                'Config key [components] must be array, %s given.',
                get_debug_type($components),
            ));
        }

        $resolved = [];

        foreach ($components as $name => $componentClass) {
            if (!is_string($name) || trim($name) === '') {
                throw new LogicException(sprintf(
                    'Config key [components] must use non-empty string keys, %s given.',
                    get_debug_type($name),
                ));
            }

            if (!is_string($componentClass) || trim($componentClass) === '') {
                throw new LogicException(sprintf(
                    'Component [%s] must be a non-empty class-string, %s given.',
                    $name,
                    get_debug_type($componentClass),
                ));
            }

            if (!class_exists($componentClass)) {
                throw new LogicException(sprintf(
                    'Component [%s] references non-existing class [%s].',
                    $name,
                    $componentClass,
                ));
            }

            /** @var class-string $componentClass */
            $resolved[$name] = $componentClass;
        }

        return $resolved;
    }
}
