<?php

declare(strict_types=1);

return [
    'robots' => [
        'enabled' => false,
        'route' => '/robots.txt',
        'header' => [
            'enabled' => true,
            'generator' => 'Lemonade Framework',
            'date_format' => 'Y-m-d H:i:s',
        ],
        'rules' => [
            '*' => [
                'allow' => ['/'],
                'disallow' => [],
            ],
        ],
        'sitemaps' => ['/sitemap.xml'],
    ],
    'sitemap' => [
        'enabled' => false,
        'route' => '/sitemap.xml',
        'mode' => 'stream',
        'base_url' => null,
        'routes' => [],
        'providers' => [],
        'cache_path' => 'storage/cache/discovery',
        'filename' => 'sitemap.xml',
        'index_filename' => 'sitemap.xml',
        'gzip' => false,
        'max_urls_per_file' => 50000,
        'max_uncompressed_bytes' => 52428800,
        'deduplicate' => false,
        'on_invalid_url' => 'fail',
    ],
];
