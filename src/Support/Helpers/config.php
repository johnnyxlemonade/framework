<?php

declare(strict_types=1);

use Lemonade\Framework\Core\Config;

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = service(Config::class);

        if (!$config instanceof Config) {
            return $default;
        }

        if ($key === null || $key === '') {
            return $config->all();
        }

        return $config->get($key, $default);
    }
}
