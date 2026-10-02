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

### Streamed responses and downloads

`Responses::stream()` accepts a zero-argument producer which returns an iterable of string chunks.
The producer is lazy: it is first evaluated while the response body is emitted, and the framework
does not collect the iterable before sending its first chunk.

```php
use Psr\Http\Message\ResponseInterface;

public function export(): ResponseInterface
{
    return $this->responses->stream(static function (): iterable {
        yield "first chunk\n";
        yield "second chunk\n";
    }, contentType: 'text/plain; charset=UTF-8');
}
```

The callback must yield strings; it must not write with `echo`. A streamed body is one-pass and is
not seekable. Its size is unknown, so `stream()` does not infer a `Content-Length` header.

The standard response emitter reads every readable PSR-7 body in 64 KiB chunks. Therefore a
`download()` response keeps its file stream, `Content-Disposition`, content type, known
`Content-Length`, and cache headers without the framework materializing the file. Normal text,
HTML, and JSON responses continue to use ordinary PSR-7 bodies.

This is a framework-memory guarantee, not an immediate network-delivery guarantee: PHP, the web
server, and reverse proxies may buffer output independently. The framework does not flush output
buffers or set server-specific buffering headers. Producer exceptions happen during emission, after
the kernel response pipeline has completed, so the emitter does not replace them with an error
response. Before the first body chunk an outer integration may still handle the exception; once a
chunk has been emitted, the status and headers cannot reliably be changed into an error page.

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
