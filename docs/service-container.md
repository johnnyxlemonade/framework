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
$url = service('url');
$validator = service('validator');
$filesystem = service('filesystem');
```

The global `service()` helper delegates to the current framework container. When the container is not available, or when the requested service does not exist, it returns the provided default value.

```php
$logger = service('logger', null);
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
