<?php

declare(strict_types=1);

use Lemonade\Framework\Session\Flash\FlashBagInterface;

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        $bag = service(FlashBagInterface::class);

        if (!$bag instanceof FlashBagInterface) {
            return $default;
        }

        return $bag->pull($key, $default);
    }
}
