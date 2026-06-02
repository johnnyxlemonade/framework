# Upgrade Notes

## Global service-backed helpers were removed

Global service-backed helpers no longer resolve services from the framework container. The framework does not boot a static container bridge, and helper functions must not be used as service locators.

Use explicit dependencies instead:

| Removed helper | Replacement |
| --- | --- |
| `asset()` | `$helpers->asset()` in views, or inject `BaseUrlResolver` |
| `url()` | `$helpers->url()` in views, `$this->url()` in controllers, or inject `UrlGenerator` |
| `localized_url()` | `$helpers->localizedUrl()` in views, or inject `UrlGenerator` |
| `csrf_field()` | `$helpers->csrfField()` in views, or inject `CsrfViewHelper` |
| `csrf_token()` | `$helpers->csrfToken()` in views, or inject `CsrfViewHelper` |
| `lang()` | `$helpers->lang()` in views, or inject `TranslatorInterface` |
| `old()` | `$requestHelpers->old()` in views, or inject session/flash services |
| `flash()` | `$requestHelpers->flash()` in views, `$this->flash()` in controllers, or inject `FlashBagInterface` |
| `current_url()` | `$requestHelpers->currentUrl()` in views, or pass `ServerRequestInterface` explicitly |
| `is_route_active()` | `$requestHelpers->isRouteActive()` in views, or inject `UrlGenerator` and pass request context explicitly |
| `service(...)` | Constructor DI, provider factory injection, or ControllerServices |

Related helpers such as `current_path()`, `current_query()`, `current_full_url()`, `is_url_active()`, `current_locale()`, `lang_group()`, `lang_all()`, `base_path()`, `app_path()`, `storage_path()`, `event()`, `queue()` and `config()` are also removed runtime API. Replace them with explicit dependencies or request/view helper objects.

The remaining global helpers are container-free utilities only.
