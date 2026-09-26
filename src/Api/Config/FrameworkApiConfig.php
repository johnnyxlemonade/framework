<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

final readonly class FrameworkApiConfig
{
    public function __construct(
        public bool $enabled,
        public ApiEndpointConfig $health,
        public ApiEndpointConfig $openapi,
        public ApiEndpointConfig $docs,
    ) {}
}
