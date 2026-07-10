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
