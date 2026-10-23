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

New controllers should inject the request-scoped `ViewRendererInterface`. Its `render()` method
returns an HTML PSR-7 response, while `content()` returns the rendered string when a controller
needs to compose a response itself.

```php
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

final class HomeController
{
    public function __construct(
        private readonly ViewRendererInterface $views,
    ) {}

    public function index(): ResponseInterface
    {
        return $this->views->render('home.index', ['title' => 'Homepage']);
    }
}
```

The renderer owns a request-local clone of the legacy `View`. PHP templates therefore continue to
receive `$this` as `View` and may use `extend()`, `partial()`, `start()`, `end()`, `section()` and
`content()` unchanged. The same object now delegates common localization, URL, asset, image and
CSRF operations to its request-scoped `ViewHelpers` instance. Request helpers and request-scoped
components are isolated per renderer scope.

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

## View Helpers

Common helpers may be called directly through `$this`. `View` delegates each operation to
`ViewHelpers`; it does not duplicate helper behavior or resolve services dynamically.

```php
<link rel="stylesheet" href="<?= htmlspecialchars($this->asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">

<a href="<?= htmlspecialchars($this->url('home'), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($this->lang('navigation.home'), ENT_QUOTES, 'UTF-8') ?>
</a>

<a href="<?= htmlspecialchars($this->localizedUrl('article.detail', ['id' => 123]), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($this->lang('article.detail'), ENT_QUOTES, 'UTF-8') ?>
</a>

<?= $this->csrfField() ?>
```

The request-scoped renderer still shares the explicit `$helpers` variable for backward
compatibility, so existing templates do not need to change. Both APIs use the same
`ViewHelpers` object and therefore the same locale, image configuration and session-backed CSRF
state.

`asset()` returns a root-relative URL such as `/css/app.css`; rendering a view therefore does not
require `app.base_url`, `allowed_hosts`, or proxy configuration. Code that genuinely needs an
absolute URL must use `BaseUrlResolver`, which accepts only explicit `app.base_url` or an
allowlisted request host.

`csrfField()` is the canonical form helper. It emits the fixed public
`CsrfTokenNames::FORM_FIELD` name and a session-scoped token; templates must not repeat that field
name manually.

## Request View Helpers

Request URL, old-input and flash operations are exposed through `$requestHelpers`. This object is
created for the current renderer scope and is not shared with another request.

`$requestHelpers` remains separate from `$helpers` so request metadata and transient input APIs do
not expand the general template helper surface.

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

Every request-local renderer clone receives:

- view helpers
- component registry
- request URL and flash helpers
- CSRF helper

The root `View` prototype retains only application-scoped collaborators. Request bindings are
attached only while constructing the request-local clone, and cloning that instance again discards
the helpers and request data. Layouts and partials render on the same request-local instance, so
they observe the same locale and CSRF session. Calling a delegated helper on a directly constructed
or otherwise unbound `View` throws a `LogicException`; there is no global-container fallback.

The view layer is intentionally simple. It is suitable for classic server-rendered PHP templates
and administration interfaces.
