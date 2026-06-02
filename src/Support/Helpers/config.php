<?php

declare(strict_types=1);

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        throw new LogicException('The global config() helper no longer resolves framework services. Inject Config explicitly.');
    }
}
