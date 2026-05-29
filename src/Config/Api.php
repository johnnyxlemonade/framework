<?php

declare(strict_types=1);

return [
    'enabled' => true,
    'prefix' => '/api',
    'endpoint_providers' => [],
    'security' => [
        'static_bearer' => [
            'enabled' => false,
            'token' => null,
            'scopes' => [
                'api:admin',
            ],
        ],
    ],
    'framework' => [
        'enabled' => true,
        'health' => [
            'enabled' => true,
            'route' => '/framework/health',
            'access' => 'public',
        ],
        'openapi' => [
            'enabled' => true,
            'route' => '/framework/openapi.json',
            'access' => 'protected',
            'scopes' => [
                'openapi:read',
            ],
        ],
        'docs' => [
            'enabled' => false,
            'route' => '/framework/docs',
            'access' => 'protected',
            'scopes' => [
                'openapi:read',
            ],
        ],
    ],
];
