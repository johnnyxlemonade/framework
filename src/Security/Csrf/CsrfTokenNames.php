<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

final class CsrfTokenNames
{
    /** Public HTML form field contract. */
    public const FORM_FIELD = 'LEMONADE_CSRF';

    /** Same-origin AJAX request header contract. */
    public const HEADER = 'X-CSRF-Token';
}
