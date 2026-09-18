# Views

The view module provides a simple PHP view renderer with shared helpers.

The default base path is:

```text
app/Views
```

## Provider-owned view resources

A provider can register an explicit, namespaced view root during bootstrap. The framework has no
knowledge of application modules or directory conventions; the provider supplies both the stable
namespace and an existing directory.

```php
use Lemonade\Framework\View\ViewResourceRegistry;

$container->get(ViewResourceRegistry::class)->register(
    'users',
    __DIR__ . '/Resources/views',
);
```

Namespaced views use exactly `namespace::dot.notation`:

```php
$view->render('users::editor');
$view->template('admin::layouts.admin', 'users::editor');
```

The namespace must match `[a-z][a-z0-9-]*`; a namespaced view name permits only dot-separated
segments containing letters, digits, `_` and `-`. Slashes, backslashes, `..`, absolute paths and
NUL bytes are rejected. A namespace has exactly one owner: duplicate registrations fail, and no
application override or precedence layer exists.

The registry canonicalizes roots with `realpath()` and resolves a requested PHP file with the same
check, so symlinks cannot escape the registered root. Registration is bootstrap-only: the first
view resolution freezes the registry, making later registrations fail deterministically. Existing
unqualified names such as `frontend.home`, `errors/404` and `layouts.error` keep their legacy
single-root behavior unchanged.

## Rendering from a controller

```php
$html = $this->view()->render('home/index', [
    'title' => 'Homepage',
]);

return $this->html($html);
```

## Example view

```php
<?php

/**
 * @var \Lemonade\Framework\View\View $this
 * @var \Lemonade\Framework\View\ViewHelpers $helpers
 * @var \Lemonade\Framework\View\RequestViewHelpers $requestHelpers
 * @var array{title:string, items:list<array{id:int, name:string, url:string}>} $page
 */
?>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
```

## Explicit View Helpers

The view service provider shares an explicit `$helpers` object with templates. `$helpers` contains app-scoped helpers only, so it does not hold request, session or flash state.

```php
<link rel="stylesheet" href="<?= htmlspecialchars($helpers->asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">

<a href="<?= htmlspecialchars($helpers->url('home'), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($helpers->lang('navigation.home'), ENT_QUOTES, 'UTF-8') ?>
</a>

<a href="<?= htmlspecialchars($helpers->localizedUrl('article.detail', ['id' => 123]), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($helpers->lang('article.detail'), ENT_QUOTES, 'UTF-8') ?>
</a>

<?= $helpers->csrfField() ?>
```

`csrfField()` is the canonical form helper. It emits the fixed public
`CsrfTokenNames::FORM_FIELD` name and a session-scoped token; templates must not repeat that field
name manually.

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

## Shared services

Shared view services include:

- view helpers
- component registry
- base URL resolver
- URL generator
- CSRF helper

The view layer is intentionally simple. It is suitable for classic server-rendered PHP templates and administration interfaces.
