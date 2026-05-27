<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;

interface ServiceProviderInterface
{
    public function register(ContainerInterface $container): void;
}
