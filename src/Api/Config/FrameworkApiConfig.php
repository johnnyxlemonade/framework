<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

final class FrameworkApiConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ApiEndpointConfig $health,
        public readonly ApiEndpointConfig $openapi,
        public readonly ApiEndpointConfig $docs,
    ) {}
}
