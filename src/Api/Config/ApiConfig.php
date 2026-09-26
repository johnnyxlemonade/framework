<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;

final readonly class ApiConfig
{
    /**
     * @param list<class-string<ApiEndpointProviderInterface>> $endpointProviders
     */
    public function __construct(
        public bool $enabled,
        public string $prefix,
        public array $endpointProviders,
        public ApiSecurityConfig $security,
        public FrameworkApiConfig $framework,
    ) {}
}
