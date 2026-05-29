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
