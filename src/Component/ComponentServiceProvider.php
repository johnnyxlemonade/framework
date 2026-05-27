<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbFactory;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbRenderer;
use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Pagination\PaginationComponent;
use Lemonade\Framework\Component\Pagination\PaginationFactory;
use Lemonade\Framework\Component\Pagination\PaginationRenderer;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ComponentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(BreadcrumbFactory::class, static function (ContainerInterface $container): BreadcrumbFactory {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new BreadcrumbFactory(
                frontendRootLabel: $config->string('breadcrumbs.frontend.root_label', 'Domu') ?? 'Domu',
                frontendRootUrl: $config->string('breadcrumbs.frontend.root_url', '/') ?? '/',
                adminRootLabel: $config->string('breadcrumbs.admin.root_label', 'Admin') ?? 'Admin',
                adminRootUrl: $config->string('breadcrumbs.admin.root_url', '/admin') ?? '/admin',
            );
        });

        $container->singleton(BreadcrumbRenderer::class, static function (ContainerInterface $container): BreadcrumbRenderer {
            /** @var Config $config */
            $config = $container->get(Config::class);
            $classes = $config->array('breadcrumbs.classes', []);

            return new BreadcrumbRenderer(self::normalizeClasses($classes));
        });

        $container->singleton(BreadcrumbComponent::class, BreadcrumbComponent::class);
        $container->singleton(PaginationFactory::class, static function (ContainerInterface $container): PaginationFactory {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new PaginationFactory(
                request: $container->get(ServerRequestInterface::class),
                defaultPerPage: $config->int('pagination.default_per_page', 20),
                maxPerPage: $config->int('pagination.max_per_page', 200),
            );
        });
        $container->singleton(PaginationRenderer::class, static function (ContainerInterface $container): PaginationRenderer {
            /** @var Config $config */
            $config = $container->get(Config::class);
            $classes = $config->array('pagination.classes', []);

            return new PaginationRenderer(
                classes: self::normalizeClasses($classes),
                translator: $container->get(TranslatorInterface::class),
                visiblePages: $config->int('pagination.visible_pages', 5),
                showFirstLast: $config->bool('pagination.show_first_last', true),
            );
        });
        $container->singleton(PaginationComponent::class, PaginationComponent::class);
        $container->singleton(MetaComponent::class, static function (ContainerInterface $container): MetaComponent {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new MetaComponent($config);
        });

        $container->singleton(ComponentRegistry::class, static function (ContainerInterface $container): ComponentRegistry {
            $registry = new ComponentRegistry($container);

            $registry->register('breadcrumb', BreadcrumbComponent::class);
            $registry->register('pagination', PaginationComponent::class);
            $registry->register('meta', MetaComponent::class);

            return $registry;
        });
    }

    /**
     * @param array<mixed> $classes
     * @return array<string, string>
     */
    private static function normalizeClasses(array $classes): array
    {
        $normalized = [];
        foreach ($classes as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
