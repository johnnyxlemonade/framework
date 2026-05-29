<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

enum ApiAccess: string
{
    case Public = 'public';
    case Protected = 'protected';
    case DebugOnly = 'debug_only';
    case InternalOnly = 'internal_only';
}
