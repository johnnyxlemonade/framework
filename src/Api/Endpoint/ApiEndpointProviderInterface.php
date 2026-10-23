<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

/**
 * Contributes application-owned endpoints to the canonical API registry during provider boot.
 */
interface ApiEndpointProviderInterface
{
    /**
     * Registers the provider's complete endpoint set without dispatching requests.
     */
    public function register(ApiEndpointRegistry $registry): void;
}
