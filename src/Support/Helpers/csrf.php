<?php

declare(strict_types=1);

use Lemonade\Framework\Security\Csrf\CsrfViewHelper;

if (!function_exists('csrf_field')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->csrfField().
     */
    function csrf_field(string $name = 'default'): string
    {
        $csrf = service(CsrfViewHelper::class);

        if (!$csrf instanceof CsrfViewHelper) {
            return '';
        }

        return $csrf->field($name);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->csrfToken().
     */
    function csrf_token(string $name = 'default'): string
    {
        $csrf = service(CsrfViewHelper::class);

        if (!$csrf instanceof CsrfViewHelper) {
            return '';
        }

        return $csrf->token($name);
    }
}
