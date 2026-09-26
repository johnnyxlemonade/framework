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

A service provider implements `ServiceProviderInterface` and receives the framework container through its `register()` method. Inside that method it can register transient bindings, singleton bindings, factories, concrete objects or string aliases.

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

When bootstrap is initiated by `Kernel::run($request)`, the exact current `ServerRequestInterface` is bound in the container before application providers register. Providers may use it for narrowly scoped pre-routing decisions. Calling `Kernel::bootstrap()` directly remains requestless and does not create an HTTP request.

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
