<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Cookie;

/**
 * Controls whether session cookies follow the verified request scheme or always require Secure.
 */
enum SessionCookieSecure: string
{
    case Auto = 'auto';
    case Always = 'always';
}
