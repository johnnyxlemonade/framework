# Service Container

The framework container is PSR-11 compatible and supports explicit bindings, singleton bindings and conservative autowiring.

`ContainerInterface` is the runtime resolver contract and exposes only `get()` and `has()`.
`ContainerBuilderInterface` is the bootstrap-only registration contract. It owns `set()`,
`singleton()`, `scoped()`, `transient()`, `instance()`, aliases, decorators, contextual bindings
and tags. Scope lifecycle belongs to `ScopeFactoryInterface::beginScope()`, not to
`ContainerInterface`.

Services are registered through a builder with `set()` or `singleton()`.

```php
$builder->set(Foo::class, Foo::class);

$builder->singleton(Bar::class, static function (ContainerInterface $container): Bar {
    return new Bar($container->get(Foo::class));
});
```

Both methods accept a class name, a factory callable or a concrete object as the implementation.

```php
$builder->set(FooInterface::class, Foo::class);

$builder->singleton(Bar::class, static function (ContainerInterface $container): Bar {
    return new Bar($container->get(FooInterface::class));
});

$builder->singleton('custom.service', new CustomService());
```

## Definition planning

Explicit registrations are represented internally as immutable service definitions and compiled into
a container plan before resolution. This preserves the existing `set()`, `singleton()`, `tag()` and
`tagged()` API while separating registration metadata from lazy runtime resolution. The plan does
not generate PHP code and does not change the singleton, transient, factory or autowiring contracts.

`compile()` creates a plan snapshot while registration is still open. `freeze()` creates the final
plan and closes the builder permanently for this process. After `freeze()`, every definition
mutation—including definitions, aliases, decorators, contextual bindings and tags—fails with
`ContainerFrozenException`; the plan is no longer invalidated or rebuilt. Bootstrap freezes the
builder after all providers have registered and before bootable providers run.

Service providers receive `ContainerBuilderInterface` for definition registration. Runtime
resolution and side effects belong in a bootable provider's `boot(ContainerInterface $container)`
phase after all providers have registered and the builder is frozen.

## Service lifetimes and scopes

`set()` and `transient()` create a fresh service value for every resolution. `singleton()` caches a
service in the root container. `scoped()` caches a service only in the active request, command or
job scope.

```php
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Container\ScopeKind;
use Psr\Http\Message\ServerRequestInterface;

$builder->scoped(CurrentRequestContext::class, CurrentRequestContext::class);

$scope = $scopeFactory->beginScope(ScopeKind::Request);

try {
    $scope->bindScopedInstance(ServerRequestInterface::class, $request);
    $context = $scope->get(CurrentRequestContext::class);
} finally {
    $scope->close();
}
```

`ScopeFactoryInterface::beginScope()` returns a `ScopedContainerInterface`. A scoped service cannot
be resolved from the root container. Within one scope, its complete decorated result is cached;
separate scopes receive separate instances. Singleton instances remain cached in and shared by the
root container across all scopes, while transient services are always rebuilt.

`close()` discards the scope cache and is idempotent. A closed scope cannot resolve further
services. Factories, callable decorators and contextual bindings receive the active runtime
container, so a scoped dependency remains in the same scope throughout resolution.

`ScopedContainerInterface::bindScopedInstance()` adds an object value only to the current scope.
It takes precedence over root bindings and is discarded by `close()`. Each scope automatically
binds itself under both `ContainerInterface::class` and `ScopedContainerInterface::class`, so
runtime factories and services can retrieve the active scope without changing the root container.
`hasScopedBinding()` distinguishes this scope-local overlay from a root binding when an integration
must enforce a request-local contract.

`ScopedContainerInterface` is a runtime resolver and scope-local binding boundary, not a service
registration API. `bindScopedInstance()` is its only write API; it adds an object only to the active
scope. It has no root-definition mutators. Middleware, controllers, commands and job handlers must
register root services during provider registration instead. This prevents a runtime mutation in a
long-running queue worker from affecting later jobs.

A singleton must never depend on a scoped service, directly or through a contextual binding or
alias. The container rejects that resolution with a dedicated exception. Scoped services may depend
on singletons, and transients may depend on scoped services only when they are resolved from an
active scope.

`Kernel::run()` automatically creates a `Request` scope, binds the PSR-7 request only in that scope,
and closes it in `finally`, including error and health-fast-path responses. HTTP middleware,
dispatch and controller resolution use this active scope. `CliKernel` creates a `Command` scope
only for a selected command, binding `CommandContext`, `CommandInput` and `CommandOutput`; list,
help and unknown-command handling remain scope-free. Queue class-string handlers run in an
isolated `Job` scope with `JobContext`, plus `QueuedMessage` for asynchronously dequeued work.
Synchronous queue dispatch also creates its own Job scope instead of reusing an active Request
scope. Legacy queue callables remain compatible, but are not resolved through the container.

`EventDispatcherInterface` is a scoped runtime service. Its listener invoker resolves listener
classes from the active Request, Command or Job scope, so listener dependencies may use values
from that scope. Root dispatch is not a normal runtime model; resolve and dispatch events from an
active scope.

This replaces the older root-container request binding that was available before application
providers registered. The replacement is intentionally breaking: `register()` and `boot()` must not
read a current request. Put request-dependent decisions in middleware, a scoped service, or a
controller instead. The root container must never contain a request-specific
`ServerRequestInterface` binding.

`ViewRendererInterface` is a request-scoped service. Inject it into a controller or another
request-scoped consumer; resolving it from the root container fails like every scoped service.

HTTP runtime is scope-capable by contract: the kernel requires a container implementing
ScopeFactoryInterface, and framework HTTP providers require ContainerBuilderInterface to declare
their scoped definitions. This is intentional; the framework does not provide a fallback for
containers that cannot create request scopes.

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

## Service decorators

Definition providers may wrap an explicit service with `decorate()`. A callable decorator receives
the runtime container and the previous service value. Class decorators implement
`ServiceDecoratorInterface`; their `decorate()` method receives the previous value explicitly while
their constructor can use normal autowiring.

```php
$builder->singleton(InvoiceImporter::class, InvoiceImporter::class);
$builder->decorate(
    InvoiceImporter::class,
    static fn(ContainerInterface $container, mixed $inner): CachedInvoiceImporter => new CachedInvoiceImporter($inner),
    priority: 100,
);
```

Decorators are applied by descending priority; equal priorities retain registration order. Decoration
always attaches to the canonical ID, so decorating an alias affects its target. The complete
decorated chain is cached for singleton and instance definitions; transient definitions rebuild both
their base service and decorator chain for every resolution.

## Contextual bindings

Contextual bindings provide an explicit dependency or constructor-parameter value for one consumer
only. They take precedence over ordinary service bindings and autowiring, and never infer values
from parameter names, environment variables or configuration files.

```php
$builder->when(BackofficeExporter::class)
    ->needs(ClockInterface::class)
    ->give(FrozenClock::class);

$builder->when(CsvImporter::class)
    ->parameter('delimiter')
    ->value(';');

$builder->when(S3Storage::class)
    ->parameter('config')
    ->config(StorageConfig::class);
```

`give()` resolves a service ID or class through the container, invokes an explicit closure or
non-object callable factory, or uses an explicit object. An invokable object is still an explicit
object value, not a factory. `value()` supplies a literal parameter value. `config()` resolves the
named typed config service normally; it does not read configuration or environment state directly.

Scalar parameters are always explicit: use `parameter()->value()`,
`parameter()->config()`, a constructor default, or an explicit factory. The framework never maps a
scalar from its parameter name, an environment variable, or a configuration key automatically.

## Tagged services

Tags declare an explicit service as a member of a collection capability. They are not filesystem
discovery, reflection scanning, interface autowiring, or constructor self-registration: the owning
provider still explicitly binds every service and explicitly declares each capability membership.

```php
$builder->singleton(ArticlesRouteRegistrar::class, ArticlesRouteRegistrar::class);
$builder->tag(ArticlesRouteRegistrar::class, RouteRegistrarInterface::class);
```

`tag()` accepts only an explicitly bound service (`set()` or `singleton()`); tagging an autowire
fallback service fails fast. The same `serviceId + tag` pair is rejected, while one service may
have multiple tags and one tag may contain multiple services. Runtime tag lookup is deliberately
separate from PSR-11: a consumer that needs it depends on `TaggedServicesInterface`; its
`tagged($tag)` method resolves services in declaration order and preserves ordinary singleton
semantics.

For the common singleton case, `singletonTagged()` is equivalent to `singleton()` followed by one
or more `tag()` calls; it does not implement a second binding mechanism:

```php
$builder->singletonTagged(
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

String service identifiers are supported and are used by some framework providers as stable binding
IDs. A string service ID is not a service alias; explicit aliases use
`ContainerBuilderInterface::alias()`.

```php
$builder->singleton('custom.service', new CustomService());
```

New application and framework code should resolve services through constructor DI, service
provider factories, explicit view data, or `$helpers` / `$requestHelpers` in views. Controllers
receive their dependencies through constructor injection; action parameters receive request and
route values.

In views, use the shared helper objects:

```php
<link rel="stylesheet" href="<?= htmlspecialchars($helpers->asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
```

## Autowiring

The default container policy is permissive:

```yaml
module: container
config:
  autowire: permissive
```

Autowiring is intentionally limited:

- unbound concrete classes can be instantiated through reflection
- class-typed constructor parameters can be resolved recursively
- interfaces must be explicitly bound in the container
- scalar and builtin constructor parameters must have default values, an explicit contextual
  `parameter()->value()` or `parameter()->config()` binding, or be provided by a factory
- non-instantiable classes fail early
- missing services fail with a service-not-found exception

Concrete autowiring is supported container behavior in this mode. It is transient and does not emit a warning or write diagnostics.

Set `autowire: strict` when an application requires every resolved service to have an explicit definition. In strict mode, an unbound concrete class fails with the standard service-not-found exception. Explicit bindings continue to support aliases, factories, singleton and scoped lifecycles, decorators, tags and contextual bindings.

## Explicit registrations

Register a service explicitly when it needs an interface or alias binding, a singleton or scoped lifecycle, a factory or external instance, a decorator, a tagged extension point, configuration, contextual or scalar policy, resource ownership, or stable instance identity.

Autowire-only resolution is appropriate for a concrete, instantiable, stateless class that has no special lifecycle, configuration or resource ownership, and does not participate in tags or decorators.
