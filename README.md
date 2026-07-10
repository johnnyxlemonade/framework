# Lemonade Framework

[![PHPStan](https://github.com/johnnyxlemonade/framework/actions/workflows/phpstan.yml/badge.svg)](https://github.com/johnnyxlemonade/framework/actions/workflows/phpstan.yml)
[![Tests](https://github.com/johnnyxlemonade/framework/actions/workflows/phpunit.yml/badge.svg)](https://github.com/johnnyxlemonade/framework/actions/workflows/phpunit.yml)
[![Lint](https://github.com/johnnyxlemonade/framework/actions/workflows/lint.yml/badge.svg)](https://github.com/johnnyxlemonade/framework/actions/workflows/lint.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Lemonade Framework is a modular PHP 8.1+ application framework for classic synchronous PHP applications.

It combines a PSR-based HTTP runtime, a PSR-11 compatible service container, explicit service providers, conventional application bootstrap and a shared runtime model for HTTP and CLI workloads.

The architecture is closer to an explicit application kernel than to a large full-stack ecosystem. A request enters the kernel, configuration is loaded, services are registered through providers, middleware is executed, the router resolves a controller action and the result is normalized into a PSR response.

The framework is intended for applications where predictable control flow matters more than heavy magic: internal tools, administration systems, backend integrations, cron tasks, small-to-medium web applications and legacy-friendly modernization projects.

## Status

This package is in pre-release development. Public APIs may still change before the first stable release.

## Design Goals

Lemonade Framework favors visible application flow over hidden lifecycle magic.

The framework focuses on:

- explicit service registration through providers
- PSR-compatible HTTP primitives
- predictable synchronous request lifecycle
- shared HTTP and CLI composition model
- conservative dependency injection
- pragmatic controller ergonomics
- reusable infrastructure modules
- CLI and cron-friendly execution
- strict typing and static-analysis-friendly APIs

## Runtime Model

Lemonade Framework is designed for the traditional synchronous PHP runtime model:

- PHP-FPM
- Apache mod_php
- built-in PHP development server
- standard CLI execution
- cron jobs

It is not designed as a long-running worker framework. Environments such as Swoole or RoadRunner require stricter control over shared state, request-scoped services and service lifetimes.

An HTTP request is expected to run in an isolated PHP request lifecycle.

## Installation

```bash
composer require johnnyxlemonade/framework
```

For local framework development:

```bash
composer install
```

## Requirements

Required platform requirements:

- PHP `>= 8.1`
- `ext-fileinfo`
- `ext-mbstring`

Composer installs the required PSR and Nyholm packages automatically.

Optional dependencies depend on selected modules and integrations:

- `ext-gd` for image upload re-encoding
- `ext-pdo` for the PDO database driver
- `ext-mysqli` for the MySQLi database driver
- `ext-odbc` for the ODBC database driver
- `ext-curl` for selected HTTP client implementations using cURL transport
- `guzzlehttp/guzzle` for the built-in Guzzle PSR-18 HTTP client provider
- `symfony/http-client` for the built-in Symfony PSR-18 HTTP client provider
- `php-http/curl-client` for the built-in PHP-HTTP cURL PSR-18 client provider
- `php-http/discovery` for automatic PSR-18 HTTP client discovery

## What the Framework Provides

The framework source is organized into focused modules under `src/`.

Core runtime modules:

- `Core` — application context, configuration loading, HTTP and CLI kernels, provider bootstrap, base controller and controller dispatch
- `Container` — PSR-11 compatible dependency injection container with explicit bindings, singleton services and conservative autowiring
- `Http` — PSR-7 / PSR-15 HTTP runtime, middleware stack, request handling, response helpers and response emitting
- `Routing` — route registration, route matching, route groups, localized routes, convention-based fallback routing and URL generation
- `Cli` — command interface, command registry and CLI kernel integration
- `Config` — framework default configuration, including the default provider list

Reusable infrastructure modules include cache, database, debugging, events, filesystem, localization, observability, queue, security, session, upload handling, validation, views and reusable UI/application components.

The framework does not force every module into application code directly. Modules are composed through service providers, so applications use configured services instead of manually wiring framework internals.

## Basic Usage

### HTTP entrypoint

A typical HTTP entrypoint creates an application context, creates the kernel and lets the kernel handle the current request.

```php
<?php

use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\KernelFactory;

require __DIR__ . '/../vendor/autoload.php';

$context = (new ApplicationContextFactory())->fromGlobals(
    dirname(__DIR__),
);

$kernel = (new KernelFactory())->create($context);
$kernel->handle();
```

### Routes

Routes are usually defined in `app/Config/Routing.php`.

```php
<?php

use Lemonade\Framework\Routing\Router;

return static function (Router $router): void {
    $router->getNamed('home', '/', 'HomeController@index');

    $router
        ->get('/articles/{id}', 'ArticleController@detail')
        ->name('article.detail');
};
```

### Controller

Controllers extend `Lemonade\Framework\Core\Controller`.

```php
<?php

namespace App\Controllers;

use Lemonade\Framework\Core\Controller;
use Psr\Http\Message\ResponseInterface;

final class HomeController extends Controller
{
    public function index(): ResponseInterface
    {
        return $this->html('<h1>Hello</h1>');
    }
}
```

Controller actions may return a PSR response directly. Scalar, stringable and `null` return values are normalized into HTML responses.

### Service Provider

Application services are registered through providers.

```php
<?php

namespace App\Providers;

use App\Services\InvoiceImporter;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class AppServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(InvoiceImporter::class, InvoiceImporter::class);
    }
}
```

Application providers are configured in `app/Config/Providers.yaml`.

```yaml
module: providers
config:
  providers:
    - App\Providers\AppServiceProvider
```

### Configuration

Application config uses YAML as the primary app-level format, but the framework still loads typed config definitions internally.

`app/Config/Config.yaml` declares the shared and entrypoint-specific config files:

```yaml
shared:
  - App
  - Providers
  - Api
http:
  - HtmlMinify
cli:
  - Commands
```

Each YAML file is only an input adapter. It is mapped to a concrete `*ConfigDefinition` class, then resolved through the existing typed resolvers and runtime DTOs. YAML is not the internal core config model.

```yaml
module: api
config:
  enabled: true
  prefix: /api
  framework:
    docs:
      enabled: true
```

Environment-backed values use explicit YAML directives:

```yaml
module: app
config:
  base_url:
    $env: APP_BASE_URL
    type: string
    default: http://localhost
```

If an application needs a custom config definition that is not covered by the built-in registry, `app/Config/ConfigMap.php` can be used as an advanced extension point to map YAML file aliases to `ConfigDefinitionInterface` classes. This is not part of the primary happy-path application setup.

### CLI Command

CLI commands implement `CommandInterface` and are configured in `app/Config/Commands.yaml`.

```php
<?php

namespace App\Console;

use Lemonade\Framework\Cli\CommandInterface;

final class ImportProductsCommand implements CommandInterface
{
    public function name(): string
    {
        return 'products:import';
    }

    public function description(): string
    {
        return 'Import products from the configured source.';
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        // ...

        return 0;
    }
}
```

```yaml
module: commands
config:
  commands:
    - App\Console\ImportProductsCommand
```

Run commands through:

```bash
vendor/bin/lemonade
vendor/bin/lemonade list
vendor/bin/lemonade products:import
```

## Documentation

Detailed documentation lives in [docs/index.md](docs/index.md).

## Code Quality

The project is designed to be static-analysis friendly.

Development scripts:

```bash
composer lint
composer stan
composer stan:ci
composer cs:check
composer cs:fix
composer qa
composer check
```

`composer check` runs full validation including Composer validation, platform checks, syntax linting, coding standards, PHPStan and tests.

## Development Server

For local development:

```bash
php -S localhost:8000 -t .
```

Then open:

```text
http://localhost:8000
```

## License

MIT License. See `LICENSE` for details.
