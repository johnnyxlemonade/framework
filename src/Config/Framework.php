<?php

declare(strict_types=1);

use Lemonade\Framework\Component\ComponentServiceProvider;
use Lemonade\Framework\Database\DatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Mysql\MysqlDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Odbc\OdbcDatabaseServiceProvider;
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
    'framework' => [
        'providers' => [
            LocalizationServiceProvider::class,
            RoutingServiceProvider::class,
            SecurityServiceProvider::class,
            DatabaseServiceProvider::class,
            MysqlDatabaseServiceProvider::class,
            OdbcDatabaseServiceProvider::class,
            SessionServiceProvider::class,
            ComponentServiceProvider::class,
            ValidationServiceProvider::class,
            UploadServiceProvider::class,
            ViewServiceProvider::class,
            EventServiceProvider::class,
            QueueServiceProvider::class,
        ],
    ],
];
