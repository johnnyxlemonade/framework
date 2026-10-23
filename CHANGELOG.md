# Changelog

This project has not published tagged releases. This section summarizes notable unreleased framework changes.

## Unreleased

- **Breaking security change:** Replaced executable PHP application-config cache files with
  authenticated, versioned `.cache` data envelopes. A separate atomically initialized local key,
  private permissions, storage/public-root separation, symbolic-link rejection, bounded typed data,
  and atomic publication now protect cached credentials. Legacy `application-*.php` artifacts are
  ignored and never executed; filesystem failures that cannot be secured now throw explicitly.
- **Breaking security change:** Replaced database queue PHP serialization with bounded, versioned
  JSON envelopes authenticated by a queue-specific HMAC key. Database messages now require an
  explicit stable alias and `MessageCodecInterface` registration, and database queue resolution
  requires `app.secret`; synchronous queues remain zero-configuration. Legacy serialized rows are
  never decoded and are quarantined as opaque failed-job payloads. Failed transitions are atomic,
  diagnostics are bounded, and conditional reservation continues to prevent duplicate delivery.
- Replaced executable PHP file-cache payloads with versioned `.cache` data envelopes authenticated by
  HMAC-SHA-256. File cache values are deserialized only after integrity and metadata verification;
  zero-configuration installations receive an atomically initialized private local key. Cache roots,
  files, containment, symbolic links, payload sizes, atomic publication, namespace isolation, and
  permissions are now enforced. Legacy `*.php` cache entries are invalidated and never loaded.
- Added central upload risk enforcement and public/private storage visibility. Public uploads now
  reject active, executable, and unclassified formats; private uploads require explicit
  `allowed_risks` authorization for non-passive formats and remain outside the web root.
- Added mandatory private upload staging with streamed actual-size enforcement, sealed snapshot
  validation, complete image decoding, and atomic no-clobber publication with rollback. The public
  `UploadFactory::file()` and `UploadFactory::image()` workflows remain unchanged.
- Added filesystem-atomic chunk quotas, per-session chunk and context limits, single-use completion,
  stable idempotent publication tokens, race-safe session retirement, automatic expired-session
  cleanup, and lease-aware orphan staging cleanup. Existing chunk sessions created before this
  metadata version cannot be resumed and are reclaimed after their TTL.
- Hardened concurrent cleanup by preparing new chunk sessions under a lock before atomic activation,
  skipping busy sessions without waiting, leasing staging and publication temporaries from creation,
  and reclaiming bounded publication orphans. Managed upload directory permissions are now verified
  and unsafe world-writable roots are restricted or rejected.
- Upload profile config objects are now immutable value objects with behavioral getters. Direct
  property reads must be replaced with methods such as `maxBytes()`, `visibility()`, and
  `allowedExtensions()`.
- **Breaking security change:** `MimeTypeDefinition` no longer treats an omitted risk as `Passive`,
  unsafe upload target directories are rejected, and public profiles that allowed active or
  executable content now fail during configuration resolution. Directly constructed `UploadStorage`
  instances without the framework path policy now refuse writes; applications should use `UploadFactory`.
  Upload validators, `UploadService`, staging, and storage are internal boundaries, and their direct
  construction API changed to make snapshot staging mandatory.
- Changed the framework license from MIT to Apache License 2.0 while retaining the BSD 3-Clause terms for URLify-derived transliteration maps.
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
- **Breaking:** Removed configurable container autowiring. Concrete classes always autowire; interfaces, scalars, and explicit lifecycles require their corresponding explicit bindings.
- Simplified built-in logging to canonical app, error, request, and benchmark channels with shared retention; request and benchmark file logging remain opt-in.
- **Breaking:** Removed the unused legacy `integrations` configuration module.
- **Breaking:** Simplified breadcrumbs to a generic caller-owned trail; removed application-specific root modes and breadcrumb configuration.
