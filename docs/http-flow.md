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
-> Kernel::bootstrap()
   -> load conventional config files
   -> apply runtime app config
   -> register core providers
   -> register HTTP provider
   -> register common framework providers
   -> register application providers
   -> load routes
-> Framework::run()
   -> start or continue benchmark run
   -> resolve global middleware stack
   -> execute PSR-15 middleware pipeline
   -> DispatchRequestHandler
      -> match route
      -> create controller request handler
      -> resolve route-specific middleware
      -> execute route middleware pipeline
      -> resolve controller
      -> inject current ServerRequestInterface for this dispatch cycle
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

Bootstrap happens before request dispatch. Global middleware wraps route matching and controller execution. Route-specific middleware wraps the matched controller handler.

Controller actions may return a PSR response directly. Scalar, stringable and `null` return values are normalized into HTML responses.
