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

## Definition planning

Explicit registrations are represented internally as immutable service definitions and compiled into
a container plan before resolution. This preserves the existing `set()`, `singleton()`, `tag()` and
`tagged()` API while separating registration metadata from lazy runtime resolution. The plan does
not generate PHP code and does not change the singleton, transient, factory or autowiring contracts.

Definition-based service providers receive `ContainerBuilderInterface`, which exposes only
definition registration and compilation. Runtime resolution and side effects belong in a bootable
provider's `boot(ContainerInterface $container)` phase after all providers have registered.

## Service aliases

Definition providers may declare an explicit alias through `ContainerBuilderInterface::alias()`. An
alias maps one service ID to a canonical target ID; it never creates a second binding or instance.
Alias chains are canonicalized when the container plan compiles, and cycles fail fast.

```php
$builder->singleton(InvoiceImporter::class, InvoiceImporter::class);
$builder->alias('invoice.importer', InvoiceImporter::class);
```

Resolving either ID follows the canonical service definition, so singleton aliases share identity
and transient aliases retain transient behavior. Aliases must not collide with service definitions,
must not target themselves, and must point to an existing service or autowireable concrete class at
runtime.

## Tagged services

Tags declare an explicit service as a member of a collection capability. They are not filesystem
discovery, reflection scanning, interface autowiring, or constructor self-registration: the owning
provider still explicitly binds every service and explicitly declares each capability membership.

```php
$container->singleton(ArticlesRouteRegistrar::class, ArticlesRouteRegistrar::class);
$container->tag(ArticlesRouteRegistrar::class, RouteRegistrarInterface::class);
```

`tag()` accepts only an explicitly bound service (`set()` or `singleton()`); tagging an autowire
fallback service fails fast. The same `serviceId + tag` pair is rejected, while one service may
have multiple tags and one tag may contain multiple services. `tagged($tag)` resolves services
through the normal container and returns them in tag declaration order, preserving ordinary
singleton semantics.

For the common singleton case, `singletonTagged()` is equivalent to `singleton()` followed by one
or more `tag()` calls; it does not implement a second binding mechanism:

```php
$container->singletonTagged(
    ArticlesRouteRegistrar::class,
    ArticlesRouteRegistrar::class,
    RouteRegistrarInterface::class,
);
```

Tag order is deterministic collection order, not business ordering. A consumer needing semantic
ordering must define and apply its own contract. `RouteRegistrarRegistry`, for example, sorts
registrars by `priority()` and then `id()`. Suitable future collection consumers include dashboard
widget providers, health checks and extension providers; tagging alone never activates a service.

## String service identifiers

String service identifiers are supported and are used by some framework providers as convenient aliases.

```php
$container->singleton('custom.service', new CustomService());
```

New application and framework code should resolve services through constructor DI, service provider factories, explicit view data, or `$helpers` / `$requestHelpers` in views. Controller service helpers are for controller infrastructure and common framework services; they are not a replacement for constructor DI in action controllers.

For example, use controller services instead of resolving common services globally:

```php
$validator = $this->validator();
$url = $this->url();
```

In views, use the shared helper objects:

```php
<link rel="stylesheet" href="<?= htmlspecialchars($helpers->asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
```

## Autowiring

Autowiring is available for concrete classes, but it is intentionally limited:

- unbound concrete classes can be instantiated through reflection
- class-typed constructor parameters can be resolved recursively
- interfaces must be explicitly bound in the container
- scalar and builtin constructor parameters must have default values or be provided by a factory
- non-instantiable classes fail early
- missing services fail with a service-not-found exception

The container may report concrete-class autowiring fallback usage when diagnostics are enabled. This encourages explicit service registration without removing the convenience of resolving simple concrete classes.

Fallback reporting is an explicit diagnostics policy, not a naming convention: when enabled, the
container reports every concrete-class autowiring fallback once per service ID. It never infers
reportability from a namespace, directory or class-name suffix. Applications can disable reporting
through the existing `container.autowire_fallback_warning` typed configuration.

This keeps the container useful for small object graphs while making important service wiring visible in service providers.
