<?php

declare(strict_types=1);

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        throw new LogicException('The global old() helper no longer resolves framework services. In views use $requestHelpers->old(); elsewhere inject SessionInterface or FlashBagInterface explicitly.');
    }
}
