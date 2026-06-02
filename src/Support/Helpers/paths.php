<?php

declare(strict_types=1);

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        throw new LogicException('The global base_path() helper no longer resolves framework services. Inject ApplicationContext explicitly.');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        throw new LogicException('The global app_path() helper no longer resolves framework services. Inject ApplicationContext explicitly.');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        throw new LogicException('The global storage_path() helper no longer resolves framework services. Inject ApplicationContext explicitly.');
    }
}
