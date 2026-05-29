# Components

The component registry provides shared UI/application components.

Framework components are registered automatically:

- `breadcrumb`
- `pagination`
- `meta`

Applications may register additional components through configuration. This is useful for reusable application-level UI helpers such as navigation, layout blocks, page widgets or project-specific presentation components.

## Registering Custom Components

Custom components are configured through the top-level `components` config key.

```php
<?php

use App\Component\NavigationComponent;

return [
    'components' => [
        'navigation' => NavigationComponent::class,
    ],
];
```

This can be placed in an existing loaded config file, for example:

```text
app/Config/App.php
```

Do not create a separate config file unless the application kernel is configured to load it.

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

Custom components intentionally use `get('name')` or `get('name', ExpectedClass::class)` instead of dynamic methods. This keeps the registry explicit and avoids magic method calls.

## Overriding Framework Components

Application configuration is registered after framework defaults. This means an application may override a default component by using the same component name.

```php
<?php

use App\Component\CustomBreadcrumbComponent;

return [
    'components' => [
        'breadcrumb' => CustomBreadcrumbComponent::class,
    ],
];
```

Only do this when the replacement component is compatible with the expected framework usage.

## Invalid Configuration

The `components` config key must be an array.

Each key must be a non-empty string component name. Each value must be a non-empty class-string referencing an existing component class.

Invalid examples:

```php
return [
    'components' => 'navigation',
];
```

```php
return [
    'components' => [
        '' => NavigationComponent::class,
    ],
];
```

```php
return [
    'components' => [
        'navigation' => 'MissingNavigationComponent',
    ],
];
```

Invalid configuration should fail during component registry creation with a clear exception.
