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

The view service provider shares an explicit `$helpers` object with templates. `$helpers` contains app-scoped helpers only, so it does not hold request, session or flash state.

Prefer `$helpers` over global service-backed helpers for app-scoped view concerns:

```php
<link rel="stylesheet" href="<?= htmlspecialchars($helpers->asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">

<a href="<?= htmlspecialchars($helpers->url('home'), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($helpers->lang('navigation.home'), ENT_QUOTES, 'UTF-8') ?>
</a>

<a href="<?= htmlspecialchars($helpers->localizedUrl('article.detail', ['id' => 123]), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($helpers->lang('article.detail'), ENT_QUOTES, 'UTF-8') ?>
</a>

<?= $helpers->csrfField() ?>

<input type="hidden" name="_token" value="<?= htmlspecialchars($helpers->csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
```

## Request View Helpers

Request/session-dependent helpers are exposed through `$requestHelpers`. This object is created for the current controller request, shared into the next view render, and then cleared after the top-level `render()`, `template()` or `partial()` call.

`$requestHelpers` is separate from `$helpers` so the app-scoped helper object never stores a stale request or session.

```php
<input
    name="email"
    value="<?= htmlspecialchars((string) $requestHelpers->old('email'), ENT_QUOTES, 'UTF-8') ?>"
>

<?php if ($message = $requestHelpers->flash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<a class="<?= $requestHelpers->isRouteActive('home') ? 'active' : '' ?>"
   href="<?= htmlspecialchars($helpers->url('home'), ENT_QUOTES, 'UTF-8') ?>">
    Home
</a>

<span><?= htmlspecialchars($requestHelpers->currentPath(), ENT_QUOTES, 'UTF-8') ?></span>
<span><?= htmlspecialchars($requestHelpers->currentUrl(), ENT_QUOTES, 'UTF-8') ?></span>
<span><?= htmlspecialchars($requestHelpers->currentFullUrl(), ENT_QUOTES, 'UTF-8') ?></span>
```

Global service-backed helpers such as `asset()`, `url()`, `csrf_field()`, `lang()`, `old()`, `flash()` and `current_url()` no longer resolve framework services. They are removed runtime API and will fail with an explicit exception. Use `$helpers` and `$requestHelpers` in templates.

## Shared services

Shared view services include:

- view helpers
- component registry
- base URL resolver
- URL generator
- CSRF helper

The view layer is intentionally simple. It is suitable for classic server-rendered PHP templates and administration interfaces.
