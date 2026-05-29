<?php

declare(strict_types=1);

return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => 'storage/cache/framework',
            'prefix' => 'lemonade',
            'ttl' => 300,
        ],
    ],
];
