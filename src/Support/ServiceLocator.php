<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

use Lemonade\Framework\Container\ContainerInterface;

/**
 * Helper runtime bridge for legacy global helpers.
 *
 * Use only to keep the global helper layer operational during the
 * compatibility window. Framework runtime services should receive dependencies
 * explicitly instead of reading the container through this bridge.
 *
 * @deprecated Prefer constructor DI, controller services, explicit view data,
 * or dedicated context/resolver objects.
 */
final class ServiceLocator
{
    private static ?ContainerInterface $container = null;

    private function __construct() {}

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function reset(): void
    {
        self::$container = null;
    }

    public static function container(): ?ContainerInterface
    {
        return self::$container;
    }
}
