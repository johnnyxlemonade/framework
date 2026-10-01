# Components

The component registry provides shared UI/application components.

Framework components are registered automatically:

- `breadcrumb`
- `pagination`
- `meta`

Applications may register additional components through configuration. This is useful for reusable application-level UI helpers such as navigation, layout blocks, page widgets or project-specific presentation components.

## Registering Custom Components

Custom components are configured through app-level YAML which maps into `ComponentConfigDefinition`.

This typically lives in:

```text
app/Config/Components.yaml
```

```yaml
module: components
config:
  navigation: App\Component\NavigationComponent
```

## Component Class Example

```php
<?php

declare(strict_types=1);

namespace App\Component;

final class NavigationComponent
{
    /**
     * @return list<array{label: string, url: string}>
     */
    public function items(): array
    {
        return [
            [
                'label' => 'Home',
                'url' => '/',
            ],
            [
                'label' => 'Articles',
                'url' => '/articles',
            ],
        ];
    }
}
```

## Resolving Components

Custom components are resolved through the component registry.

```php
use App\Component\NavigationComponent;
use Lemonade\Framework\Component\ComponentRegistry;

final class LayoutController
{
    public function __construct(
        private readonly ComponentRegistry $components,
    ) {}

    public function navigation(): array
    {
        /** @var NavigationComponent $navigation */
        $navigation = $this->components->get('navigation');

        return $navigation->items();
    }
}
```

When using typed lookup, the expected component class can be passed directly:

```php
$navigation = $components->get('navigation', NavigationComponent::class);
```

This avoids local PHPDoc annotations and keeps usage static-analysis friendly.

## Framework Components

Framework components have explicit typed accessors:

```php
$components->breadcrumb();
$components->pagination();
$components->meta();
```

These accessors exist only for framework-provided components.

## Breadcrumbs

Breadcrumbs are a generic presentation component. The application creates the
trail and supplies already-localized labels and application-generated URLs; the
framework does not define application areas, roots, labels, or routes.

~~~php
$trail = $components->breadcrumb()
    ->empty()
    ->add('Home', '/')
    ->add('Documentation', '/documentation');
~~~

The renderer owns the semantic BreadcrumbList markup, canonical structural
classes, escaping, and the active final item. Applications that need a
fundamentally different rendering contract may replace the breadcrumb
component through the existing component override configuration.

`pagination()` is request-scoped because its factory derives page and URL defaults from the
current request. Resolve it from a controller, route middleware, or the `$component` registry
provided to a controller-rendered view; do not resolve pagination from a root provider or other
bootstrap code.

Custom components intentionally use `get('name')` or `get('name', ExpectedClass::class)` instead of dynamic methods. This keeps the registry explicit and avoids magic method calls.

## Overriding Framework Components

Application configuration is registered after framework defaults. This means an application may override a default component by using the same component name.

```yaml
module: components
config:
  breadcrumb: App\Component\CustomBreadcrumbComponent
```

Only do this when the replacement component is compatible with the expected framework usage.

## Invalid Configuration

Each component name must be a non-empty string. Each component class must be a non-empty class-string referencing an existing component class.

Invalid examples:

```yaml
module: components
config:
  "": App\Component\NavigationComponent
```

```yaml
module: components
config:
  navigation: MissingNavigationComponent
```

Invalid configuration should fail during component registry creation with a clear exception.
