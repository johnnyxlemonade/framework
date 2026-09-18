# Service Providers

Service providers are the main composition mechanism for framework and application services.

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
