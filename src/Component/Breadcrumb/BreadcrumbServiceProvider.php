<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

use Lemonade\Framework\Component\Breadcrumb\Config\BreadcrumbsConfig;
use Lemonade\Framework\Component\Breadcrumb\Config\BreadcrumbsConfigDefinition;
use Lemonade\Framework\Component\Breadcrumb\Config\BreadcrumbsConfigResolver;
use Lemonade\Framework\Component\Support\ComponentConfig;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class BreadcrumbServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(BreadcrumbsConfigResolver::class, BreadcrumbsConfigResolver::class);
        $container->singleton(BreadcrumbsConfig::class, static function (ContainerInterface $container): BreadcrumbsConfig {
            return $container
                ->get(BreadcrumbsConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    BreadcrumbsConfigDefinition::moduleKey(),
                    BreadcrumbsConfigDefinition::class,
                ));
        });
        $container->singleton(BreadcrumbFactory::class, static function (ContainerInterface $container): BreadcrumbFactory {
            $config = $container->get(BreadcrumbsConfig::class);

            return new BreadcrumbFactory(
                frontendRootLabel: $config->frontendRootLabel,
                frontendRootUrl: $config->frontendRootUrl,
                adminRootLabel: $config->adminRootLabel,
                adminRootUrl: $config->adminRootUrl,
            );
        });

        $container->singleton(BreadcrumbRenderer::class, static function (ContainerInterface $container): BreadcrumbRenderer {
            $config = $container->get(BreadcrumbsConfig::class);

            return new BreadcrumbRenderer(ComponentConfig::normalizeClasses($config->classes));
        });

        $container->singleton(BreadcrumbComponent::class, BreadcrumbComponent::class);
    }
}
