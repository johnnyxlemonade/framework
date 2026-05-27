<?php

declare(strict_types=1);

use Lemonade\Framework\Support\ServiceLocator;

if (!function_exists('service')) {
    /**
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
