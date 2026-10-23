<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Lemonade\Framework\Api\Endpoint\BuiltInApiScope;

/**
 * Requires every endpoint scope unless the authenticated identity owns the administrative scope.
 *
 * Scope evaluation never changes or substitutes the identity trust boundary.
 */
final readonly class ScopeVoter
{
    /**
     * Reports whether the identity satisfies the complete required scope set.
     *
     * @param list<non-empty-string> $requiredScopes
     */
    public function isGranted(ApiIdentity $identity, array $requiredScopes): bool
    {
        if ($identity->hasScope(BuiltInApiScope::ApiAdmin->value)) {
            return true;
        }

        foreach ($requiredScopes as $scope) {
            if (!$identity->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }
}
