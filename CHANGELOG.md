# Changelog

## Unreleased

### Breaking changes

#### TWGS-10209: Global service helper runtime removed

- Removed the `ServiceLocator` runtime concept.
- Removed `HelperRuntime` and the static container bridge.
- The global `service()` helper was removed.
- Service-backed global helper symbols were removed instead of resolving services from the container.
- Pure/container-free helper functions remain available.

| Removed API | Replacement |
| --- | --- |
| `service(...)` | Constructor DI or provider factories; controller helper methods only for framework/controller infrastructure |
| `asset(...)` | `$helpers->asset(...)` |
| `url(...)` | `$helpers->url(...)` |
| `localized_url(...)` | `$helpers->localizedUrl(...)` |
| `csrf_field(...)` | `$helpers->csrfField(...)` |
| `csrf_token(...)` | `$helpers->csrfToken(...)` |
| `lang(...)` | `$helpers->lang(...)` |
| `old(...)` | `$requestHelpers->old(...)` |
| `flash(...)` | `$requestHelpers->flash(...)` |
| `current_url()` | `$requestHelpers->currentUrl()` |
| `is_route_active(...)` | `$requestHelpers->isRouteActive(...)` |
