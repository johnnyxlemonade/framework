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

## Explicit View Helpers

The view service provider shares an explicit `$helpers` object with templates. Prefer this object over global service-backed helpers.

```php
<link rel="stylesheet" href="<?= htmlspecialchars($helpers->asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">

<a href="<?= htmlspecialchars($helpers->url('home'), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($helpers->lang('navigation.home'), ENT_QUOTES, 'UTF-8') ?>
</a>

<?= $helpers->csrfField() ?>
```

## Shared services

Shared view services include:

- view helpers
- component registry
- base URL resolver
- URL generator
- CSRF helper

The view layer is intentionally simple. It is suitable for classic server-rendered PHP templates and administration interfaces.
