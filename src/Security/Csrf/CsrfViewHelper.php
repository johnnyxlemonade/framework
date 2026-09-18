<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

final class CsrfViewHelper
{
    public function __construct(
        private readonly CsrfTokenManager $tokens,
    ) {}

    public function token(string $name = 'default'): string
    {
        return $this->tokens->token($name);
    }

    public function field(string $name = 'default'): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            CsrfTokenNames::FORM_FIELD,
            htmlspecialchars($this->token($name), ENT_QUOTES, 'UTF-8'),
        );
    }

    public function fieldName(): string
    {
        return CsrfTokenNames::FORM_FIELD;
    }
}
