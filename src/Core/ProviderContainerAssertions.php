<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerBuilderInterface;

/** @internal Shared provider registration assertions. */
final class ProviderContainerAssertions
{
    public static function builder(ContainerBuilderInterface $container, string $provider, string $purpose): ContainerBuilderInterface
    {
        unset($provider, $purpose);
        return $container;
    }
}
