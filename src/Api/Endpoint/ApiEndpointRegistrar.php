<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Routing\Router;

final class ApiEndpointRegistrar
{
    public function __construct(
        private readonly Router $router,
        private readonly ApiEndpointRegistry $registry,
    ) {}

    public function registerRoutes(string $prefix): void
    {
        $prefix = $this->normalizePrefix($prefix);

        foreach ($this->registry->all() as $endpoint) {
            $this->router
                ->map($endpoint->method(), $prefix . $endpoint->path(), $endpoint->handler())
                ->name($endpoint->name());
        }
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = '/' . trim($prefix, '/');

        return $prefix === '/' ? '' : rtrim($prefix, '/');
    }
}
