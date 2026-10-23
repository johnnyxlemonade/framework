<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

/**
 * Selects the authentication boundary enforced before an API endpoint is dispatched.
 *
 * Internal access requires an explicitly trusted identity in addition to ordinary scope checks.
 */
enum ApiAccess: string
{
    case Public = 'public';
    case Protected = 'protected';
    case DebugOnly = 'debug_only';
    case InternalOnly = 'internal_only';
}
