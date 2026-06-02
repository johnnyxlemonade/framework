# Service Container

The framework container is PSR-11 compatible and supports explicit bindings, singleton bindings and conservative autowiring.

Services are registered with `set()` or `singleton()`.

```php
$container->set(Foo::class, Foo::class);

$container->singleton(Bar::class, static function (ContainerInterface $container): Bar {
    return new Bar($container->get(Foo::class));
});
```

Both methods accept a class name, a factory callable or a concrete object as the implementation.

```php
$container->set(FooInterface::class, Foo::class);

$container->singleton(Bar::class, static function (ContainerInterface $container): Bar {
    return new Bar($container->get(FooInterface::class));
});

$container->singleton('custom.service', new CustomService());
```

## String service identifiers

String service identifiers are supported and are used by some framework providers as convenient aliases.

```php
$container->singleton('custom.service', new CustomService());
```

New application and framework code should resolve services through constructor DI, service provider factories, controller services, explicit view data, or `$helpers` / `$requestHelpers` in views.

For example, use controller services instead of resolving common services globally:

```php
$validator = $this->validator();
$url = $this->url();
```

In views, use the shared helper objects:

```php
<link rel="stylesheet" href="<?= htmlspecialchars($helpers->asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
```

## Removed `service()` helper

The global `service()` helper no longer resolves services from the framework container.

Do not use global service resolving in new code. Use constructor DI, service provider factories, controller services, explicit view data, `$helpers` or `$requestHelpers`.

```php
// Removed:
// $logger = service('logger', null);

// Prefer:
public function __construct(private LoggerInterface $logger) {}
```

## Autowiring

Autowiring is available for concrete classes, but it is intentionally limited:

- unbound concrete classes can be instantiated through reflection
- class-typed constructor parameters can be resolved recursively
- interfaces must be explicitly bound in the container
- scalar and builtin constructor parameters must have default values or be provided by a factory
- non-instantiable classes fail early
- missing services fail with a service-not-found exception

The container may report autowiring fallback usage for selected application and framework services when diagnostics are enabled. This encourages explicit service registration without removing the convenience of resolving simple concrete classes.

This keeps the container useful for small object graphs while making important service wiring visible in service providers.
