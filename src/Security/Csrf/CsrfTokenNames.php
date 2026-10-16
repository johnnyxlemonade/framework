<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

/**
 * Defines the stable transport names used to submit CSRF tokens.
 */
final class CsrfTokenNames
{
    /**
     * Identifies the CSRF token field in submitted HTML forms.
     */
    public const string FORM_FIELD = 'LEMONADE_CSRF';

    /**
     * Identifies the request and response header carrying a CSRF token.
     */
    public const string HEADER = 'X-CSRF-Token';
}
