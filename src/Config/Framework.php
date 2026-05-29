<?php

declare(strict_types=1);

use Lemonade\Framework\Component\ComponentServiceProvider;
use Lemonade\Framework\Database\DatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Mysql\MysqlDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Odbc\OdbcDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteDatabaseServiceProvider;
use Lemonade\Framework\Discovery\DiscoveryServiceProvider;
use Lemonade\Framework\Debug\DebugServiceProvider;
use Lemonade\Framework\Event\EventServiceProvider;
use Lemonade\Framework\Localization\LocalizationServiceProvider;
use Lemonade\Framework\Queue\QueueServiceProvider;
use Lemonade\Framework\Routing\RoutingServiceProvider;
use Lemonade\Framework\Security\SecurityServiceProvider;
use Lemonade\Framework\Session\SessionServiceProvider;
use Lemonade\Framework\Upload\UploadServiceProvider;
use Lemonade\Framework\Validation\ValidationServiceProvider;
use Lemonade\Framework\View\ViewServiceProvider;

return [
    'app' => [
        'timezone' => null,
    ],
    'framework' => [
        'providers' => [
            LocalizationServiceProvider::class,
            RoutingServiceProvider::class,
            DiscoveryServiceProvider::class,
            SecurityServiceProvider::class,
            DatabaseServiceProvider::class,
            MysqlDatabaseServiceProvider::class,
            OdbcDatabaseServiceProvider::class,
            PdoDatabaseServiceProvider::class,
            SqliteDatabaseServiceProvider::class,
            SessionServiceProvider::class,
            ComponentServiceProvider::class,
            ValidationServiceProvider::class,
            UploadServiceProvider::class,
            DebugServiceProvider::class,
            ViewServiceProvider::class,
            EventServiceProvider::class,
            QueueServiceProvider::class,
        ],
    ],
    'cors' => [
        'enabled' => false,
        'allowed_origins' => [],
        'allowed_methods' => [],
        'allowed_headers' => [],
        'exposed_headers' => [],
        'allow_credentials' => false,
        'max_age' => null,
    ],
    'discovery' => [
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
    ],
];
