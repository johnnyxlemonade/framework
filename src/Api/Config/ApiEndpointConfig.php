<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiAccess;

final readonly class ApiEndpointConfig
{
    /**
     * @param list<non-empty-string> $scopes
     */
    public function __construct(
        public bool $enabled,
        public string $route,
        public ApiAccess $access,
        public array $scopes = [],
    ) {}
}
