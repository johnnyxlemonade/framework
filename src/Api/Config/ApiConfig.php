<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;

final class ApiConfig
{
    /**
     * @param list<class-string<ApiEndpointProviderInterface>> $endpointProviders
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $prefix,
        public readonly array $endpointProviders,
        public readonly ApiSecurityConfig $security,
        public readonly FrameworkApiConfig $framework,
    ) {}
}
