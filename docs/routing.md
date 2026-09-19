# Routing

Routes are registered through the router, usually in `app/Config/Routing.php`.

The router maps HTTP methods and paths to controller actions using the `Controller@action` format.

## Provider-owned route registrars

An application route file owns application composition routes. A provider that owns a
specialized route capability can contribute typed routes without adding its routes to the
central application route file:

```php
<?php

use Lemonade\Framework\Routing\RouteRegistrarInterface;
use Lemonade\Framework\Routing\Router;

final class ArticlesRouteRegistrar implements RouteRegistrarInterface
{
    public function id(): string
    {
        return 'cms.articles';
    }

    public function priority(): int
    {
        return 100;
    }

    public function registerRoutes(Router $router): void
    {
        $router->group('/admin/articles', static function (Router $router): void {
            $router->getNamed('admin.articles.import', '/import', 'ArticlesImportController@form');
        });
    }
}
```

The owning service provider registers the registrar as a service and tags it as a route capability:

```php
$container->singletonTagged(
    ArticlesRouteRegistrar::class,
    ArticlesRouteRegistrar::class,
    RouteRegistrarInterface::class,
);
```

The Kernel resolves services tagged with `RouteRegistrarInterface::class` during route
finalization and passes them to `RouteRegistrarRegistry`. The registry remains public for explicit
advanced use-cases, but providers do not manually register ordinary DI services. A tagged service
that does not implement `RouteRegistrarInterface` fails fast with its service ID and tag in the
diagnostic.

Registrars execute after application composition routes. They are ordered deterministically by
`priority()` ascending and then `id()` ascending. IDs must be globally unique. A provider without
specialized routes simply does not register a route registrar.

The HTTP and CLI kernels own the finalization lifecycle:

```text
Router exists
→ providers bind services and tag typed route registrars
→ application Routing.php registers application/shared routes
→ framework resolves tagged registrars and executes route registrars
→ registrar registry freezes
→ router freezes
→ dispatch
```

`Routing.php` must not execute or freeze the registrar registry itself. After finalization,
registering another registrar or mutating router configuration, routes, names, middleware, or
parameter constraints fails fast. Router read operations, URL generation, and dispatch remain
available.

## Route conflicts and ordering

The router rejects an exact duplicate method and normalized path, for example two `GET /admin/foo`
routes. It also rejects duplicate route names regardless of whether a name is set through
`getNamed()`/`mapNamed()` or `map(...)->name(...)`.

The framework intentionally does not attempt to detect every semantic overlap between dynamic
patterns such as `/foo/{id}` and `/foo/{slug}`. Such overlaps must be intentional. Registrar
priority makes provider registration deterministic; it does not infer the correct meaning of
ambiguous dynamic routes.

## Basic routes

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

Controller names are resolved against the application controller namespace unless a fully qualified class name is used.

## Named routes and URL generation

Named routes can be used for URL generation:

```php
$url = $this->url()->route('article.detail', ['id' => 123]);
```

Extra parameters become query parameters.

```php
$url = $this->url()->route('article.detail', [
    'id' => 123,
    'preview' => 1,
]);
```

In views, use the explicit shared helper object:

```php
<a href="<?= htmlspecialchars($helpers->url('article.detail', ['id' => 123]), ENT_QUOTES, 'UTF-8') ?>">
    Detail
</a>
```

Legacy global helper resolving remains available for existing applications, but new code should prefer controller services, constructor DI, or `$helpers` in views.

Result:

```text
/articles/123?preview=1
```

## Route groups

```php
$router->group('/admin', static function (Router $router): void {
    $router->getNamed('dashboard', '/dashboard', 'Admin\DashboardController@index');
});
```

## Localized route groups

```php
$router->localizedGroup(static function (Router $router): void {
    $router->getNamed('home', '/', 'HomeController@index');
});
```

Localized routes are useful when the application needs language-aware URLs while still keeping route definitions centralized.
