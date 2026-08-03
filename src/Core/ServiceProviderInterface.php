<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;

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
     * @param ContainerInterface $container Container used by the framework runtime.
     */
    public function register(ContainerInterface $container): void;
}
