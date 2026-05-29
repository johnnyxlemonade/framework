# Views

The view module provides a simple PHP view renderer with shared helpers.

The default base path is:

```text
app/Views
```

## Rendering from a controller

```php
$html = $this->view()->render('home/index', [
    'title' => 'Homepage',
]);

return $this->html($html);
```

## Example view

```php
<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
```

## Shared services

Shared view services include:

- component registry
- base URL resolver
- URL generator
- CSRF helper

The view layer is intentionally simple. It is suitable for classic server-rendered PHP templates and administration interfaces.
