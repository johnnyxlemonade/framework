<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiAccess;

final class ApiEndpointConfig
{
    /**
     * @param list<non-empty-string> $scopes
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $route,
        public readonly ApiAccess $access,
        public readonly array $scopes = [],
    ) {}
}
