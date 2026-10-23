<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

/**
 * Defines stable permission names used by framework-owned API policies.
 *
 * The administrative scope satisfies ordinary scope checks but does not establish internal trust.
 */
enum BuiltInApiScope: string
{
    case ApiRead = 'api:read';
    case ApiWrite = 'api:write';
    case ApiDelete = 'api:delete';
    case ApiAdmin = 'api:admin';

    case FrameworkRead = 'framework:read';
    case FrameworkDiagnostics = 'framework:diagnostics';

    case OpenApiRead = 'openapi:read';

    case TokensRead = 'tokens:read';
    case TokensWrite = 'tokens:write';
    case TokensRevoke = 'tokens:revoke';
}
