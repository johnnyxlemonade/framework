<?php

declare(strict_types=1);

use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $session = service(SessionInterface::class);

        if ($session instanceof SessionInterface) {
            $value = $session->get('_old_input.' . $key, null);
            if ($value !== null) {
                return $value;
            }
        }

        $flash = service(FlashBagInterface::class);

        if (!$flash instanceof FlashBagInterface) {
            return $default;
        }

        $values = $flash->get('old_input', []);
        if (!is_array($values)) {
            return $default;
        }

        return $values[$key] ?? $default;
    }
}
