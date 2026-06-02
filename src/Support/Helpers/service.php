<?php

declare(strict_types=1);

use Lemonade\Framework\Support\ServiceLocator;

if (!function_exists('service')) {
    /**
     * Low-level compatibility helper for legacy convenience helpers.
     *
     * New framework/runtime code should prefer constructor DI, controller
     * services, explicit view data, or dedicated context/resolver objects.
     *
     * @deprecated This helper is retained as a BC bridge for service-backed
     * global helpers and application code during migration.
     *
     * @param class-string|non-empty-string $id
     */
    function service(string $id, mixed $default = null): mixed
    {
        $container = ServiceLocator::container();

        if ($container === null || !$container->has($id)) {
            return $default;
        }

        return $container->get($id);
    }
}
