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
        'openapi' => [
            'enabled' => true,
            'access' => 'protected',
            'scopes' => [
                'openapi:read',
            ],
        ],
        'docs' => [
            'enabled' => false,
            'access' => 'protected',
            'scopes' => [
                'openapi:read',
            ],
        ],
    ],
];
