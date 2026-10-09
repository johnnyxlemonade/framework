<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registers the stateless breadcrumb renderer and component for application use.
 */
final class BreadcrumbServiceProvider implements ServiceProviderInterface
{
    /**
     * Adds breadcrumb runtime services without requiring application configuration.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(BreadcrumbRenderer::class, BreadcrumbRenderer::class);
        $container->singleton(BreadcrumbComponent::class, BreadcrumbComponent::class);
    }
}
