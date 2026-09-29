<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerBuilderInterface;

/**
 * Defines the contract for framework service providers.
 *
 * Service providers register services, aliases and related dependencies
 * in the application container during framework bootstrap.
 */
interface ServiceProviderInterface
{
    /**
     * Registers the provider's services in the application container.
     *
     * @param ContainerBuilderInterface $container Container definition builder.
     */
    public function register(ContainerBuilderInterface $container): void;
}
