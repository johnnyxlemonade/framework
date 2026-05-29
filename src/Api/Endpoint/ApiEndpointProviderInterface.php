<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

interface ApiEndpointProviderInterface
{
    public function register(ApiEndpointRegistry $registry): void;
}
