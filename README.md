# Lemonade Framework

Lemonade Framework is a modular PHP 8.1+ framework built around PSR-based HTTP runtime, a PSR-11 compatible dependency injection container, provider-based bootstrap, and a CLI kernel.

The framework is designed for classic PHP web applications, internal tools, backend integrations, and cron/CLI workloads where explicit structure, strict typing, and predictable runtime flow are preferred over heavy magic.

## Status

This package is in pre-release development. Public APIs may still change before the first stable release.

## Deployment Model

Lemonade Framework is designed for the traditional synchronous PHP runtime model, such as PHP-FPM, Apache mod_php, the built-in PHP development server, and standard CLI execution.

It is not currently designed for long-running stateful worker environments such as Swoole or RoadRunner. The framework may store request-scoped services in the container during request dispatch, so each HTTP request is expected to run in an isolated PHP request lifecycle.

## Features

- PSR-7 / PSR-15 HTTP runtime (`request -> middleware -> routing -> controller -> response`)
- PSR-11 compatible dependency injection container
- Explicit service providers and provider-based bootstrap
- Router with named routes, route parameters, groups, and URL generation
- Controller resolver with typed action argument resolution
- Response helpers for text, HTML, JSON, redirects, downloads, and streams
- View layer with shared helpers
- Localization, validation, session, upload, security, cache, database, event, queue, and filesystem modules
- CLI runtime via `CliKernel` and command registry
- Cron-friendly locking support for CLI workloads
- Benchmark and logging integration for HTTP and CLI runtime

## Runtime Flow

### HTTP

```text
index.php
-> ApplicationContextFactory::fromGlobals()
-> KernelFactory::create()
-> Framework::__construct()
-> Kernel::handle()
-> Kernel::bootstrap()
-> config files
-> core/common/configured service providers
-> routes
-> Framework::run()
-> global middleware pipeline
-> router match
-> route middleware pipeline
-> controller resolver
-> controller action
-> PSR response
-> response emitter
```

### CLI

```text
bin/lemonade
-> ApplicationContextFactory::fromGlobals()
-> Container
-> Framework
-> CliKernel::handle()
-> CliKernel::bootstrap()
-> config files + commands
-> service providers
-> command registry
-> command execution
-> exit code
```

## Runtime Modes

- Web: `index.php -> Kernel`
- CLI: `bin/lemonade -> CliKernel`

## Project Structure

- `src/` framework source code
- `app/` application/demo code during development
- `storage/` runtime data such as logs, locks, uploads, and cache
- `bin/lemonade` CLI entrypoint

The `app/` directory is used as a development/demo application and is not part of the framework source itself.

## Quick Start

Install dependencies:

```bash
composer install
```

Run the development server:

```bash
php -S localhost:8000 -t .
```

Open `http://localhost:8000`.

Run CLI commands:

```bash
php bin/lemonade
php bin/lemonade list
```

## Service Container

Services are registered through service providers and resolved through the container.

```php
use Lemonade\Framework\Routing\UrlGenerator;

$url = service(UrlGenerator::class);
```

String aliases are available for selected framework services:

```php
$url = service('url');
$filesystem = service('filesystem');
```

Constructor autowiring is intentionally conservative. Interfaces must be explicitly bound and scalar constructor parameters require default values or a factory binding.

### PSR-11 note

The container implements `Psr\Container\ContainerInterface`.

Its `has()` method returns `true` when a service is explicitly bound or when the given class exists and can potentially be resolved through autowiring. To check only for explicit framework/container bindings, use `isBound()`.

```php
$container->has(Foo::class);
// true if Foo is explicitly bound or can potentially be autowired

$container->isBound(Foo::class);
// true only if Foo was explicitly registered in the container
```

## Routing

Routes are registered against the router:

```php
$router->get('/', 'HomeController@index')->name('home');
$router->get('/articles/{id}', 'ArticleController@detail')->name('article.detail');
```

Named routes can be generated through the URL generator:

```php
$url = service('url')->route('article.detail', ['id' => 123]);
```

## Configuration

Configuration files return PHP arrays and are loaded during kernel bootstrap.

Example localization environment variables:

```bash
APP_LOCALE=cs
APP_FALLBACK_LOCALE=cs
APP_SUPPORTED_LOCALES=cs,en
```

Example config file:

```php
use Lemonade\Framework\Support\Env;

return [
    'localization' => [
        'default_locale' => Env::string('APP_LOCALE', 'cs'),
        'fallback_locale' => Env::string('APP_FALLBACK_LOCALE', 'cs'),
        'supported_locales' => Env::list('APP_SUPPORTED_LOCALES', ['cs', 'en']),
    ],
];
```

## Code Quality

The project is checked with PHPStan on level 10, including PHPStan bleeding edge rules and phpstan-strict-rules.

Common development scripts:

```bash
composer lint
composer stan
composer stan:ci
composer cs:check
composer cs:fix
composer qa
```

Current CI checks:

- PHPStan static analysis on PHP 8.1, 8.2, 8.3, 8.4, and 8.5
- PHP CS Fixer coding standards check
- PHP syntax linting on PHP 8.1, 8.2, 8.3, 8.4, and 8.5
- Composer audit for dependency security advisories

## Requirements

- PHP `>= 8.1`
- `ext-fileinfo`
- `ext-mbstring`

Optional extensions depend on selected modules and integrations:

- `ext-gd` for image upload re-encoding
- `ext-pdo` for the PDO database driver
- `ext-mysqli` for the MySQLi database driver
- `ext-odbc` for the ODBC database driver
- `ext-curl` for selected HTTP client transports

## Database Driver vs Dialect

`driver` defines the connection backend. `dialect` defines SQL grammar and schema behavior.

Example native MySQL configuration (dialect is implicit):

```php
[
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'app',
    'username' => 'root',
    'password' => '',
]
```

Example PDO + MySQL dialect configuration:

```php
[
    'driver' => 'pdo',
    'dialect' => 'mysql',
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
]
```

PDO driver can be used for generic query execution, but schema operations currently support only the MySQL dialect via `dialect: mysql`.
Other PDO DSNs may be usable for raw queries, but schema grammar is not guaranteed unless a matching dialect implementation exists.

Example PDO + SQLite dialect configuration:

```php
[
    'driver' => 'pdo',
    'dialect' => 'sqlite',
    'dsn' => 'sqlite:/absolute/path/database.sqlite',
]
```

In-memory SQLite variant:

```php
[
    'driver' => 'pdo',
    'dialect' => 'sqlite',
    'dsn' => 'sqlite::memory:',
]
```

SQLite is supported through PDO. SQLite schema grammar is intentionally conservative; some `ALTER TABLE` operations are not supported and throw `LogicException`. Complex table changes need a dedicated rebuild-table strategy.

Example ODBC configuration (dialect is implicit):

```php
[
    'driver' => 'odbc',
    'dsn' => 'Driver={ODBC Driver 18 for SQL Server};Server=localhost;Database=app;TrustServerCertificate=yes;',
    'username' => 'sa',
    'password' => 'your_password',
]
```

## License

MIT License. See `LICENSE` for details.
