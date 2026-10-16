<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Cookie;

/**
 * Defines the browser SameSite policy supported by framework session cookies.
 */
enum SessionCookieSameSite: string
{
    case Lax = 'Lax';
    case Strict = 'Strict';
    case None = 'None';
}
