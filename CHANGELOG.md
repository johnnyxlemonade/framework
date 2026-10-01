# Changelog

This project has not published tagged releases. This section summarizes notable unreleased framework changes.

## Unreleased

- Raised the framework baseline to PHP 8.3 and expanded container/provider composition with scoped services, aliases, decorators, contextual bindings, tags, boot ordering, and dependency declarations.
- Introduced typed configuration definitions and resolvers with YAML-first application configuration.
- **Breaking:** Removed the global `service()` helper, service-backed global helpers, and the static service-locator runtime; use constructor DI, provider factories, `$helpers`, or `$requestHelpers` instead.
- Added application- and request-scoped view helpers and provider-owned namespaced views.
- Replaced the legacy validation API with typed schemas, field definitions, and rule definitions.
- Added explicit controller actions, provider-owned route registrars, locale constraints, and HEAD, OPTIONS, and CORS request handling.
- Added PDO database drivers and dialect support, migrations, shared connection handling, and nested transaction reuse.
- Added the opt-in discovery capability for `robots.txt`, sitemap generation, and the sitemap CLI command.
- Added framework API documentation endpoints and Swagger UI.
- Added image processing, MIME validation, and configurable image-variant scaling and background policies.
- Made HTML error presentation environment-driven, centralized PHP diagnostics and fatal-shutdown handling, and limited benchmark response diagnostics to development.
- Added `container.autowire` modes: permissive concrete autowiring is the default and strict mode requires explicit service definitions.
- Simplified built-in logging to canonical app, error, request, and benchmark channels with shared retention; request and benchmark file logging remain opt-in.
- **Breaking:** Removed the unused legacy `integrations` configuration module.
- **Breaking:** Simplified breadcrumbs to a generic caller-owned trail; removed application-specific root modes and breadcrumb configuration.
