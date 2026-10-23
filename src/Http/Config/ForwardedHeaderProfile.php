<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

/**
 * Selects the one forwarded-header family that trusted proxies may supply.
 */
enum ForwardedHeaderProfile: string
{
    case None = 'none';
    case Forwarded = 'forwarded';
    case XForwarded = 'x_forwarded';
}
