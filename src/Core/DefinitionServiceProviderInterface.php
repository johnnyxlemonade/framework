<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerBuilderInterface;

/**
 * Registers service definitions without resolving runtime services.
 */
interface DefinitionServiceProviderInterface
{
    public function register(ContainerBuilderInterface $builder): void;
}
