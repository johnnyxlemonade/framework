<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Lemonade\Framework\Api\Endpoint\BuiltInApiScope;

final class ScopeVoter
{
    /**
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
