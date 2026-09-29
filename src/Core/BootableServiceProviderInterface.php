<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;

/**
 * Performs runtime registry registration after the definition plan is frozen.
 */
interface BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void;
}
