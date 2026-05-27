<?php

declare(strict_types=1);

use Lemonade\Framework\Support\Env;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = Env::string($key);

        return $value ?? $default;
    }
}
