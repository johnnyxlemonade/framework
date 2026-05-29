<?php

declare(strict_types=1);

return [
    'driver' => 'native',
    'cookie' => 'LEMONADE_SESSION',
    'lifetime' => 7200,
    'native' => [
        'path' => 'writable/sessions',
    ],
    'file' => [
        'path' => 'writable/sessions',
    ],
    'database' => [
        'table' => 'sessions',
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'password' => '',
        'prefix' => 'sess:',
        'timeout' => 2.5,
    ],
];
