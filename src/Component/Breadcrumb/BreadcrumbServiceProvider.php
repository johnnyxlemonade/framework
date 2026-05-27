<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

use Lemonade\Framework\Component\Support\ComponentConfig;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class BreadcrumbServiceProvider implements ServiceProviderInterface
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

            return new BreadcrumbRenderer(ComponentConfig::normalizeClasses($classes));
        });

        $container->singleton(BreadcrumbComponent::class, BreadcrumbComponent::class);
    }
}
