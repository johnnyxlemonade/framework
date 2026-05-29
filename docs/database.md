# Database

The database layer separates connection backend from SQL/schema dialect.

The selected `driver` defines how the framework connects and executes queries. The selected or implicit `dialect` defines SQL grammar and schema behavior.

## Supported driver/provider families

- native MySQL
- ODBC
- PDO
- SQLite through PDO

## Native MySQL configuration

```php
return [
    'database' => [
        'default' => 'default',

        'connections' => [
            'default' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'app',
                'username' => 'root',
                'password' => '',
            ],
        ],
    ],
];
```

## PDO with MySQL dialect

```php
return [
    'database' => [
        'default' => 'default',

        'connections' => [
            'default' => [
                'driver' => 'pdo',
                'dialect' => 'mysql',
                'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
                'username' => 'root',
                'password' => '',
            ],
        ],
    ],
];
```

## PDO with SQLite dialect

```php
return [
    'database' => [
        'default' => 'default',

        'connections' => [
            'default' => [
                'driver' => 'pdo',
                'dialect' => 'sqlite',
                'dsn' => 'sqlite:/absolute/path/database.sqlite',
            ],
        ],
    ],
];
```

SQLite schema support is intentionally conservative. Some `ALTER TABLE` operations are not supported and should be implemented through a dedicated rebuild-table strategy.
