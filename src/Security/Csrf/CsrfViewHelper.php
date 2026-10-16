<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

/**
 * Exposes session-backed CSRF tokens and escaped hidden fields to templates.
 */
final readonly class CsrfViewHelper
{
    /**
     * Connects template operations to the canonical token manager.
     */
    public function __construct(
        private CsrfTokenManager $tokens,
    ) {
    }

    /**
     * Returns the stable session token associated with the requested logical name.
     */
    public function token(string $name = 'default'): string
    {
        return $this->tokens->token($name);
    }

    /**
     * Renders an escaped hidden input containing the requested session token.
     */
    public function field(string $name = 'default'): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            CsrfTokenNames::FORM_FIELD,
            htmlspecialchars($this->token($name), ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * Returns the public form field name recognized by the CSRF middleware.
     */
    public function fieldName(): string
    {
        return CsrfTokenNames::FORM_FIELD;
    }
}
