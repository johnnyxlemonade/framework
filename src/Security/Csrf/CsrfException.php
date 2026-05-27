<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

use RuntimeException;

final class CsrfException extends RuntimeException
{
    public static function invalidToken(): self
    {
        return new self('Invalid CSRF token.');
    }
}
