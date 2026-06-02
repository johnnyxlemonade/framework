# Changelog

## Unreleased

### Breaking changes

#### TWGS-10209: Global service helper runtime removed

- Removed the `ServiceLocator` runtime concept.
- Removed `HelperRuntime` and the static container bridge.
- The global `service()` helper no longer returns services and now throws `LogicException`.
- Service-backed global helpers are disabled and now throw `LogicException` instead of resolving services from the container.
- Pure/container-free helper functions remain available.

| Removed API | Replacement |
| --- | --- |
| `service(...)` | Constructor DI, provider factories, or ControllerServices |
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
