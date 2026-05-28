<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

use Lemonade\Framework\Container\ContainerInterface;

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
