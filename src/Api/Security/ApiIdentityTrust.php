<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

/**
 * Classifies whether an authenticated API identity may cross the internal endpoint boundary.
 *
 * Trust is assigned only by the authenticator that verified the credential; identity types and
 * scopes do not imply internal trust.
 */
enum ApiIdentityTrust: string
{
    case External = 'external';
    case Internal = 'internal';
}
