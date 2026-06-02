<?php

declare(strict_types=1);

if (!function_exists('csrf_field')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->csrfField().
     */
    function csrf_field(string $name = 'default'): string
    {
        throw new LogicException('The global csrf_field() helper no longer resolves framework services. In views use $helpers->csrfField(); elsewhere inject CsrfViewHelper explicitly.');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->csrfToken().
     */
    function csrf_token(string $name = 'default'): string
    {
        throw new LogicException('The global csrf_token() helper no longer resolves framework services. In views use $helpers->csrfToken(); elsewhere inject CsrfViewHelper explicitly.');
    }
}
