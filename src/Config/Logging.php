<?php

declare(strict_types=1);

return [
    'app' => [
        'enabled' => true,
        'path' => 'storage/writable/logs/app.log',
        'level' => 'info',
    ],
    'error' => [
        'enabled' => true,
        'path' => 'storage/writable/logs/error.log',
        'level' => 'error',
    ],
    'request' => [
        'enabled' => false,
        'path' => 'storage/writable/logs/request.log',
        'level' => 'info',
    ],
    'benchmark' => [
        'enabled' => false,
        'path' => 'storage/writable/logs/benchmark.log',
        'level' => 'debug',
    ],
];
