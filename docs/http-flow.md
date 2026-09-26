# HTTP Request Flow

Lemonade Framework uses a deliberately linear HTTP lifecycle. The request enters the kernel, configuration is loaded, services are registered, middleware is executed, routing resolves a controller action and the action result is normalized into a PSR response.

## Flow

```text
public/index.php
-> ApplicationContextFactory::fromGlobals()
-> KernelFactory::create()
-> Kernel::handle()
   -> create ServerRequest from globals when no request is provided
-> Kernel::run()
   -> create a Request scope
   -> bind the provided ServerRequestInterface only in that scope
-> Kernel::bootstrap()
   -> load conventional YAML application config files
   -> apply runtime app config
   -> register core providers
   -> register HTTP provider
   -> register common framework providers
   -> register application providers
   -> load routes
   -> resolve tagged RouteRegistrarInterface services
   -> finalize and freeze routing
-> Framework::runInScope()
   -> start or continue benchmark run
   -> resolve global middleware stack
   -> execute PSR-15 middleware pipeline
   -> DispatchRequestHandler
      -> match route
      -> create controller request handler
      -> resolve route-specific middleware
      -> execute route middleware pipeline
      -> resolve controller
      -> resolve the current scope-bound ServerRequestInterface
      -> resolve action arguments
      -> call controller action
      -> normalize result to PSR response
-> ResponseEmitter
```

## Entrypoint example

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

## Notes

Each `Kernel::run()` call creates a `Request` scope and binds its exact
`ServerRequestInterface` only in that scope. Bootstrap and application-provider registration
remain requestless: the root container never stores a request-specific binding. The scope is closed
in a `finally` block after a normal response, a not-found response, or any middleware/controller
exception. This also applies to the health fast path.

Services resolved for the request pipeline, route middleware, dispatch handler and controller use
the active scoped container. Therefore `ContainerInterface` injected into a request-scoped or
transient runtime service resolves to that `ScopedContainerInterface`; root singletons remain shared
and cannot consume request-local values.

This is a breaking change from older versions that bound `ServerRequestInterface` into the root
container before provider registration. Providers must not inspect the current request in
`register()` or `boot()`; use middleware, a scoped service, or a controller for request-dependent
decisions. Applications using the former provider-request pattern must migrate that composition
logic separately; the framework provides no root-binding compatibility shim.

`Framework::runInScope()` is the kernel integration API. It accepts only a `Request` scope whose
scope-local `ServerRequestInterface` binding is the identical object passed as its request argument.
The guard rejects command/job scopes, a missing local request binding, and a mismatched request.

Bootstrap happens before request dispatch. Global middleware wraps route matching and controller execution. Route-specific middleware wraps the matched controller handler.

Controller actions may return a PSR response directly. Scalar, stringable and `null` return values are normalized into HTML responses.
