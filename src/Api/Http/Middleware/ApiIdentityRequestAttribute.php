<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Middleware;

/**
 * Defines the stable PSR-7 request attribute key for an authorized API identity.
 */
final readonly class ApiIdentityRequestAttribute
{
    /**
     * Attribute name populated only after authentication and authorization succeed.
     */
    public const NAME = 'api.identity';

    private function __construct()
    {
    }
}
