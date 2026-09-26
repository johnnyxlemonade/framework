# Lemonade Framework

[![PHPStan](https://github.com/johnnyxlemonade/framework/actions/workflows/phpstan.yml/badge.svg)](https://github.com/johnnyxlemonade/framework/actions/workflows/phpstan.yml)
[![Tests](https://github.com/johnnyxlemonade/framework/actions/workflows/phpunit.yml/badge.svg)](https://github.com/johnnyxlemonade/framework/actions/workflows/phpunit.yml)
[![Lint](https://github.com/johnnyxlemonade/framework/actions/workflows/lint.yml/badge.svg)](https://github.com/johnnyxlemonade/framework/actions/workflows/lint.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Lemonade Framework is a modular PHP 8.3+ application framework for maintainable web applications, administration systems, CMS projects and integration-oriented services.

It combines a PSR-based HTTP runtime, a PSR-11 compatible service container, provider-based bootstrap, routing, a CLI kernel and reusable infrastructure for long-lived application development.

It is an application framework rather than a micro-framework layer. Its architecture keeps application flow explicit: a request enters the kernel, configuration is loaded, services are registered through providers, middleware is executed, the router resolves a controller action and the result is normalized into a PSR response.

The framework is intended for projects where predictable control flow and maintainable composition matter: internal tools, administration systems, CMS applications, backend integrations, cron-driven workflows and legacy-friendly modernization projects.

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
- modular infrastructure services composed through providers
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

- PHP `>= 8.3 < 8.6`
- `ext-fileinfo`
- `ext-mbstring`

Official support covers PHP 8.3, 8.4 and 8.5. The syntax and runtime target is PHP 8.3: new framework source and generated PHP source must remain PHP 8.3 compatible, even when development or CI runs on PHP 8.4 or 8.5.

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

The framework source is organized into focused modules under `src/`. Applications compose them through service providers, so configured services remain the integration boundary instead of manually wired framework internals.

### Runtime, HTTP and Routing

- `Core` — application context, HTTP and CLI kernels, provider bootstrap, controller dispatch and response normalization
- `Http` — PSR-7 request and response handling, PSR-15 middleware pipeline, response emitting and Nyholm PSR-17 integration
- HTTP middleware for errors, CORS, `OPTIONS`, request logging, benchmarks, HTML minification and response headers
- `Routing` — normalized route paths, route collections, named routes and URL generation, route groups, localized routes and convention-based fallback routing
- `Api` — configurable endpoint registry, health endpoint, OpenAPI and HTML documentation, Problem Details responses, and bearer-token scope authorization

### Container, Providers and Configuration

- `Container` — PSR-11 compatible dependency injection with explicit bindings, singleton services and conservative autowiring
- `ServiceProviderInterface` — explicit registration and composition of framework, application and integration services
- `Config` — YAML application configuration mapped to typed definitions and runtime DTOs, environment values and production config caching
- package extension points for application providers, API endpoint providers, event listeners, sitemap providers, views and components

### CLI and Operations

- `Cli` and `bin/lemonade` — command interface, command registry and a CLI kernel sharing the configured application services
- command-driven operations such as migrations, queue installation and workers, and sitemap generation; suitable for cron-driven tasks
- `Discovery` — configurable `robots.txt`, sitemap and sitemap-index generation

### Data, State and Integration

- `Database` — PDO, MySQLi and ODBC drivers, schema tools and migrations; no ORM is required
- `Cache` — PSR-6 cache pools with file, array and null stores
- `Filesystem` and `Session` — storage and session services
- `Event` — in-memory event dispatcher with registered listeners and priorities
- `Queue` — synchronous and database-backed transports, message serialization, delayed jobs and worker commands
- optional PSR-18 HTTP client providers for Guzzle, Symfony HTTP Client and PHP-HTTP cURL transport

### Application Building Blocks

- `Localization` and `Validation` — file-backed translation catalogs, locale-aware routing support and validation rules
- `Security` — CSRF token management, middleware and view helpers
- `Upload` — profile-based file and image uploads, MIME and dimension validation, storage, and optional GD re-encoding
- `View` and `Component` — view resources and helpers plus reusable pagination, breadcrumb and metadata components
- `Logging`, `Debug` and `Observability` — PSR-3 logging, diagnostics and request/database benchmark data

### Support Utilities

- `Lemonade\Framework\Support\Slug\Slugger` — a URLify-inspired best-effort ASCII slug generator with curated transliteration maps; it is intended for URL slugs, not as a general Unicode transliterator
- environment and base-URL helpers, escaping and formatting helpers, clock abstractions and XML stream writing

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

Controllers extend `Lemonade\Framework\Core\AbstractController`.

```php
<?php

namespace App\Controllers;

use Lemonade\Framework\Core\AbstractController;
use Psr\Http\Message\ResponseInterface;

final class HomeController extends AbstractController
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

In production, application config definitions are compiled automatically into entrypoint-specific generated PHP cache files under `storage/cache/framework/config/`. Development and testing continue to load config directly from source YAML. See [docs/configuration.md](docs/configuration.md) for cache lifecycle, invalidation, and deployment workflow details.

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

## ORM Integration

Lemonade does not include or require an ORM. [The live Doctrine ORM example](https://lemonadeframework.cz/en/examples/orm) is an optional application-level integration registered through an application service provider and the container.

It demonstrates Doctrine ORM with SQLite, `ManyToMany` relations, author and tag filtering with faceted counts, pagination, and `toIterable()` for the main article feed.

## Testing

The framework uses complementary testing techniques:

- **Property-based testing (Eris)** generates varied input datasets and verifies invariants across a broader input space than example-based tests alone.
- **Mutation testing (Infection)** applies small changes to production code and checks whether the test suite detects the resulting behavioral changes. Manual local suites cover the routing core, container invariants and the `Slugger` utility.

Run the standard quality suite with:

```bash
composer qa
```

Property-based tests can also run separately:

```bash
composer test:property
```

Run routing mutation tests with:

```bash
composer test:mutation:routing
```

Run container mutation tests, including tagged-service collection invariants, with:

```bash
composer test:mutation:container
```

Run manual Slugger mutation tests with Xdebug coverage enabled:

```bash
composer test:mutation:slugger
```

Regenerate local API documentation with Doctum:

```bash
composer docs:api
```

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
