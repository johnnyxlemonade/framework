# Service Providers

Service providers are the main composition mechanism for framework and application services.

## Provider lifecycle

New providers should separate service definitions from runtime side effects. A
`DefinitionServiceProviderInterface` receives `ContainerBuilderInterface` in `register()` and
should only declare bindings and tags. It cannot resolve services through that contract.

```php
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\DefinitionServiceProviderInterface;

final class BillingProvider implements DefinitionServiceProviderInterface
{
    public function register(ContainerBuilderInterface $builder): void
    {
        $builder->singleton(InvoiceImporter::class, InvoiceImporter::class);
    }
}
```

Providers that need a fully registered runtime container implement
`BootableServiceProviderInterface`. Their `boot()` method runs after all core, framework and
application providers have registered, in provider declaration order. Use it for routes, commands,
migrations, listeners and other runtime registry side effects.

```php
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;

final class BillingRoutesProvider implements BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void
    {
        $container->get(BillingRouteRegistry::class)->registerRoutes();
    }
}
```

The existing `ServiceProviderInterface::register(ContainerInterface $container)` remains fully
supported for compatibility. Legacy providers may continue to use the container exactly as before,
including immediate resolution where their established behavior requires it. New code should keep
`register()` definition-only and move runtime side effects to `boot()`.

## Provider dependencies

A provider can implement `DependentServiceProviderInterface` to declare provider classes that must
run first. Dependencies are sorted before either `register()` or `boot()` runs. The sorter preserves
the configured order for providers that are otherwise independent, and boot uses the same resolved
order as registration.

```php
use Lemonade\Framework\Core\DependentServiceProviderInterface;

final class BillingRoutesProvider implements DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array
    {
        return [BillingProvider::class];
    }
}
```

Every dependency must be a supported provider class and must be included in the same configured
provider list. Missing, invalid and cyclic dependencies fail during bootstrap with a descriptive
exception. Provider priorities and automatic discovery are intentionally not part of this model.

A service provider implements `ServiceProviderInterface` and receives the framework container through its `register()` method. Inside that method it can register transient bindings, singleton bindings, factories, concrete objects or string service IDs. A string service ID is an ordinary binding identifier, not a service alias; definition providers declare explicit aliases through `ContainerBuilderInterface::alias()`.

## Provider example

```php
<?php

namespace App\Providers;

use App\Services\InvoiceImporter;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class AppServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(InvoiceImporter::class, InvoiceImporter::class);
    }
}
```

## Application provider configuration

Application providers are configured through:

```text
app/Config/Providers.yaml
```

```yaml
module: providers
config:
  providers:
    - App\Providers\AppServiceProvider
```

This YAML payload is mapped into `ProvidersConfigDefinition`, and the existing typed resolver pipeline still produces the runtime `ProvidersConfig` object used during bootstrap.

## Bootstrap order

Framework providers are resolved from `FrameworkConfigDefinition`. Application providers are resolved from `ProvidersConfigDefinition`.

During bootstrap, the kernel registers core framework providers first, then common framework providers, and finally application providers. This allows application code to extend or override services after the framework services have been registered.

Application providers register during requestless bootstrap. `Kernel::run($request)` creates the
request scope only after configuration loading and binds the exact `ServerRequestInterface` only in
that scope for middleware and dispatch. Providers must therefore not read or retain a current
request during `register()` or `boot()`; request-dependent work belongs in a scoped runtime service.
Calling `Kernel::bootstrap()` directly remains requestless and does not create an HTTP request.

### Breaking migration: request-aware providers

Older framework versions exposed the current `ServerRequestInterface` as a root-container binding
before application providers registered. That pattern has been removed: a request-specific value in
the root container can leak into a singleton or a later request. It is a breaking change for a
provider that called `isBound(ServerRequestInterface::class)` or `get(ServerRequestInterface::class)`
during `register()` or `boot()`.

Do not restore this pattern. Move request-dependent decisions to runtime middleware, a scoped
service, or a controller. `ServerRequestInterface` is available only from the active `Request`
scope; provider registration and boot remain requestless composition phases.

## Translation resources

Providers may contribute file translation resources without copying them into the application
`Language/` directory. The provider itself registers a resource root with
`TranslationResourceRegistry` during its `register()` method. Each resource uses the conventional
`<locale>/<group>.php` layout.

```php
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;

final class ExampleServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $translationResources = $container->get(TranslationResourceRegistry::class);
        $translationResources->register(__DIR__ . '/Resources/lang');
    }
}
```

Catalogs are merged with this exact precedence:

```text
framework translations
→ provider resources in registration order
→ application translations
```

For a conflicting translation key, the last registered provider resource wins. Application
translations always provide the final override. Merging happens per translation key, not by
replacing an entire group, so unrelated keys from earlier resources remain available.

The resource root must exist and be a directory. Equivalent paths, including paths with a trailing
slash or a symlink to the same directory, are canonicalized and registered only once.

Resources are a bootstrap-time contribution: register them during provider registration before the
translator is first used. Once a translator reads a catalog, further resource registration is
rejected to avoid inconsistent cached catalogs. Enabling, disabling, installing, or removing a
provider therefore takes effect on the next application bootstrap; a provider absent from bootstrap
does not contribute translations.

`TranslatorInterface::all()` exposes the merged server-side catalog. Consumers may later build an
explicit export mechanism on top of it, but the framework does not automatically publish server
translations to clients.

This is a provider contribution: registering a provider makes its translations available to
`TranslatorInterface` automatically.
