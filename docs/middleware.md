# Middleware

The HTTP runtime uses a PSR-15 middleware pipeline.

Global middleware wraps route matching and controller execution. Route-specific middleware wraps only the matched controller handler.

## Default middleware stack

The default middleware stack contains framework middleware for:

- request logging
- benchmarking
- error handling
- CORS
- powered-by headers
- HTML minification
- OPTIONS handling

## Global middleware

Application code can add middleware globally during framework configuration:

```php
use Lemonade\Framework\Http\Middleware\MiddlewareStack;

$framework->middleware(static function (MiddlewareStack $stack): void {
    $stack->add(App\Http\Middleware\AuthMiddleware::class);
});
```

## Route-specific middleware

Route-specific middleware is resolved from the container and executed around the matched controller action.

Middleware classes should implement `Psr\Http\Server\MiddlewareInterface`.

```php
<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Perform authentication here.

        return $handler->handle($request);
    }
}
```

## JSON response preference

`Content-Type` describes the format of the request body; it does not determine the requested
response format. Use `HttpRequestInspector::wantsJson()` to inspect `Accept` when middleware,
controllers, validation, or error handling needs a JSON-or-HTML decision. `wantsJson()` returns
true only when a JSON media type has a positive quality value and is at least as preferred as the
other accepted media types. It recognizes `application/json` and media types ending in `+json`.
Missing `Accept` and `Accept: */*` alone return false.

```php
use Lemonade\Framework\Http\Request\HttpRequestInspector;

if ($this->requestInspector->wantsJson($request)) {
    // Return a JSON response.
}

// Return an HTML response or redirect.
```

Controllers can use the equivalent protected `$this->wantsJson()` helper. This is distinct from
the existing `expectsJson()` helper, which is intentionally broader and may also consider request
body format or AJAX request characteristics.

## CSRF response token contract

Unsafe CSRF-protected requests (`POST`, `PUT`, `PATCH`, `DELETE`) validate the session-scoped
synchronizer token. The token is generated from secure random bytes on first use and remains stable
for the lifetime of that session; every response that passes `CsrfMiddleware` carries it in
`X-CSRF-Token`. This lets HTML forms, AJAX mutations and concurrently opened pages safely share one
token. An invalid token returns `419`
without executing the handler and includes the current token for resynchronisation. Authentication
code may explicitly regenerate the token after a session/identity boundary; session invalidation
naturally invalidates it.

Only `GET`, `HEAD` and `OPTIONS` bypass CSRF validation. The middleware reads the public HTML form
field `LEMONADE_CSRF` first and otherwise the `X-CSRF-Token` header; it never reads a token from the
query string. If both body and header are present, the body field is authoritative.

`CsrfTokenNames::FORM_FIELD` and `CsrfTokenNames::HEADER` are the canonical fixed framework
transport contract for those names. They are intentionally not application configuration: views,
same-origin JavaScript and middleware must use the same stable protocol.
