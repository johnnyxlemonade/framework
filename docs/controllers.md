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

For server-rendered pages, inject `ViewRendererInterface`. It is scoped with the request and
returns an HTML PSR-7 response directly.

```php
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

final class HomeController
{
    public function __construct(private readonly ViewRendererInterface $views) {}

    public function index(): ResponseInterface
    {
        return $this->views->render('home.index');
    }
}
```

For text, JSON, redirects, downloads and streamed responses, inject the root-safe `Responses`
facade.

```php
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;

final class AccountController
{
    public function __construct(private readonly Responses $responses) {}

    public function store(): ResponseInterface
    {
        return $this->responses->json(['ok' => true], 201);
    }
}
```

`Responses` provides `html()`, `text()`, `json()`, `redirect()`, `download()` and `stream()`.

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
methods before invocation. Routes must use an explicit `ControllerAction::for(Controller::class, 'method')`
mapping; string handlers and convention-based action resolution do not exist.

Route parameters are injected by parameter name and cast to scalar types when possible.

```php
public function detail(int $id): ResponseInterface
{
    // ...
}
```

## Controller model

The framework supports plain constructor-injected controller classes only. There is no base
controller, mutable controller context or controller service locator. Inject dependencies through
the constructor and declare request and route values as action parameters.
