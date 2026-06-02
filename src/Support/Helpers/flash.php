<?php

declare(strict_types=1);

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        throw new LogicException('The global flash() helper no longer resolves framework services. In views use $requestHelpers->flash(); in controllers use $this->flash() or inject FlashBagInterface explicitly.');
    }
}
