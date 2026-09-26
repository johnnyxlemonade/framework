# Controllers

New controllers should normally be plain, `final` classes with explicit constructor
dependencies. The framework resolves a controller from the active Request scope, so
request-local services can be injected safely without binding them into the root
container.

Controller actions may return a PSR response directly. Scalar, stringable and `null`
return values are normalized into HTML responses.

## Preferred controller style

Use constructor injection for application and framework dependencies. Keep only the
methods intended for HTTP dispatch public.

```php
<?php

namespace App\Controllers;

final class HomeController
{
    public function __construct(
        private readonly HomePage $homePage,
    ) {}

    public function index(): string
    {
        return $this->homePage->render();
    }
}
```

The controller resolver creates the controller in the Request scope. A request-local
dependency may be injected through the constructor, or supplied as an action parameter
when that better expresses that it belongs only to that action.

For a matched route, an action receiving `ServerRequestInterface` gets the same request instance
that route middleware received. Its `RouteRequestAttributes::MATCH` attribute contains the
immutable `RouteMatch` with the matched controller, action, parameters and, for named explicit
routes, route name.

```php
use Psr\Http\Message\ServerRequestInterface;

public function store(ServerRequestInterface $request): ResponseInterface
{
    // ...
}
```

## Action visibility

An action method must be `public`. The resolver rejects `protected` and `private`
methods before invocation. This applies both to explicit `Controller@action` route
mappings and to convention-based action resolution.

Route parameters are injected by parameter name and cast to scalar types when possible.

```php
public function detail(int $id): ResponseInterface
{
    // ...
}
```

## `AbstractController` convenience facade

`Lemonade\Framework\Core\AbstractController` remains available as a convenience facade,
especially for quick server-rendered controllers that benefit from its request, response,
view, validation and similar helpers. It is not the required or preferred base type for
new controllers.

The facade receives a mutable runtime `ControllerContext` during dispatch. Do not bind a
controller extending `AbstractController` as a singleton, and do not reuse an instance
between requests. Use the default transient resolution or an explicit scoped binding for
controllers that need request-local state.

```php
use Lemonade\Framework\Core\AbstractController;
use Psr\Http\Message\ResponseInterface;

final class LegacyPageController extends AbstractController
{
    public function index(): ResponseInterface
    {
        return $this->html('<h1>Hello</h1>');
    }
}
```

The facade provides helpers for common request and response tasks:

```php
$this->query('page', 1);
$this->post('name');
$this->jsonPayload();
$this->file('image');

$this->text('OK');
$this->html('<h1>OK</h1>');
$this->json(['ok' => true]);
$this->redirect('/login');
$this->download($path);
$this->stream($producer);
```

JSON response payloads must be JSON-encodable. Encoding failures throw `JsonException`
and follow the normal HTTP error-handling policy; the helper never substitutes an empty
JSON object.

It also exposes common framework helpers:

```php
$this->url();
$this->validator();
$this->translator();
$this->filesystem();
$this->view();
$this->flash();
$this->breadcrumb();
```

## Service lookup

`controllerService()` and the facade service helpers are convenience APIs around the
active controller context. In particular, `controllerService()` is a generic service
lookup and should be treated as a legacy/convenience escape hatch, not as a normal
application architecture pattern. Prefer explicit constructor DI for application
services and for dependencies that make an action's requirements clearer.

An application base controller can still collect genuinely shared rendering helpers:

```php
use Lemonade\Framework\Core\AbstractController;
use Psr\Http\Message\ResponseInterface;

abstract class AppController extends AbstractController
{
    /**
     * @param array<string, mixed> $data
     */
    protected function page(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->html(
            $this->view()->template('layouts.app', $view, $data),
            $status,
        );
    }
}
```

Concrete controllers should still declare their business dependencies explicitly:

```php
final class DocumentationController extends AppController
{
    public function __construct(
        private readonly DocumentationCatalogInterface $documentation,
    ) {}
}
```
