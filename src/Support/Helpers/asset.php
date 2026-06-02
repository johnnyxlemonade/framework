<?php

declare(strict_types=1);

if (!function_exists('asset')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->asset().
     */
    function asset(string $path): string
    {
        throw new LogicException('The global asset() helper no longer resolves framework services. In views use $helpers->asset(); elsewhere inject BaseUrlResolver explicitly.');
    }
}
