# Observability

The framework includes benchmark integration for HTTP and CLI runtime.

Benchmark marks are used to make the lifecycle visible during development and diagnostics.

## Runtime marks

The kernels and dispatch flow mark important lifecycle points such as:

- kernel start
- config loaded
- providers registered
- routes registered
- request received
- middleware enter
- route match start
- route matched
- controller resolve start
- controller resolved
- controller action start
- controller action finished
- response created
- response ready
- kernel exception

This is useful for diagnosing slow bootstrap, routing, controller execution or response generation.

## HTTP response diagnostics

Benchmark data is collected for the request lifecycle, but benchmark response exposure is development-only.
In `development`, the framework adds `X-Benchmark-*` response headers. Its optional HTML benchmark
comment is controlled independently and defaults to enabled:

```yaml
module: benchmark
config:
  inject_html_comment: true
```

In every non-development environment the framework adds neither headers nor comment, regardless of this
setting. Benchmark file logging is a separate policy controlled by `logging.benchmark.enabled`; when enabled,
it writes benchmark runs to `benchmark-YYYY-MM-DD.log`.

## Database summary

Database connections report their timing to the active benchmark run. The response includes:

- `X-Benchmark-Db-Connection-Ms` for lazy connection open and driver session setup
- `X-Benchmark-Db-Time-Ms` for SQL prepare/bind/execute time, excluding connection open
- `X-Benchmark-Db-Query-Count` for executed SQL statements

The structured `request.benchmark` log includes the same values under `database`.
When application debug mode is enabled, `database.queries` additionally contains SQL,
bindings, and per-query elapsed time. In non-debug mode that list remains empty, so
bindings are not written to production benchmark logs.

## Logging

Logging is registered early during kernel bootstrap so HTTP handling, CLI failures and kernel exceptions can use the configured logger. Application code should depend on `Psr\Log\LoggerInterface` when it needs application logging.

The framework owns the built-in channel convention and file location:

- `storage/writable/logs/app-YYYY-MM-DD.log`
- `storage/writable/logs/error-YYYY-MM-DD.log`
- `storage/writable/logs/request-YYYY-MM-DD.log`
- `storage/writable/logs/benchmark-YYYY-MM-DD.log`

All built-in channels use one retention period, with a framework default of seven days. Applications configure policy rather than filenames, sinks or per-channel rotation:

```yaml
module: logging
config:
  retention_days: 7

  request:
    enabled: false
    min_status: 0

  benchmark:
    enabled: false
```

`app` and `error` are always available. `error` is reserved for runtime and application exceptions, so ordinary HTTP 404 responses are not written there. Request logging is opt-in; when enabled, `min_status: 0` records every response, `400` records client and server failures, and `500` records server failures. This makes a 404 observable in `request.log` only when request logging is enabled and its threshold includes 404.

Benchmark file logging is opt-in and does not control development-only benchmark response diagnostics or the
HTML comment setting.
