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
use Lemonade\Framework\Core\ServiceProviderInterface;

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
        $container->singleton(ComponentRegistry::class, static function (ContainerInterface $container): ComponentRegistry {
            $registry = new ComponentRegistry($container);

            foreach (self::COMPONENTS as $name => $componentClass) {
                $registry->register($name, $componentClass);
            }

            return $registry;
        });
    }
}
