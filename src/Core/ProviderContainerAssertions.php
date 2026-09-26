<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

/** @internal Shared provider registration assertions. */
final class ProviderContainerAssertions
{
    public static function builder(
        ContainerInterface $container,
        string $providerClass,
        string $purpose,
    ): ContainerBuilderInterface {
        if (!$container instanceof ContainerBuilderInterface) {
            throw new RuntimeException(sprintf(
                '%s requires a container implementing %s to register %s.',
                $providerClass,
                ContainerBuilderInterface::class,
                $purpose,
            ));
        }

        return $container;
    }
}
