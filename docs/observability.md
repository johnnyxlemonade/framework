# Observability

The framework includes benchmark integration for HTTP and CLI runtime.

Benchmark marks are used to make the lifecycle visible during development and diagnostics.

The `Benchmark` coordinator is shared by the root container so bootstrap and long-lived infrastructure
services can report to the active lifecycle. Each HTTP request or CLI handle invocation owns a fresh
`BenchmarkRun`; completion stops the run and detaches it from the coordinator in `finally`, including
health fast-path and exception responses. A completed run cannot accept further context, marks, or
database measurements.

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

The structured `request.benchmark` log includes the same aggregate values under `database`.
SQL text and bindings are never written by the HTTP benchmark logger, including in debug mode.
Detailed query data may still exist in the request-local `BenchmarkRun` for development
diagnostics, but it does not cross the framework file-logging boundary.

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

Framework-generated HTTP request, benchmark, and error records use a fixed metadata allowlist.
They retain the normalized method, URI path without a query string, validated request ID,
response status, and elapsed time where applicable. They do not record query strings, cookies,
request bodies, form values, SQL bindings, raw exception messages and traces, or request and
response header values other than the validated request ID. This policy is identical in debug
and production modes. Development error responses may still display exception diagnostics and
must not be exposed as a production mode.

The generic PSR-3 logger remains an application logging API: values, exception objects, or
messages explicitly supplied by application code are outside the HTTP metadata boundary and are
not automatically redacted. Applications should pass an explicit non-sensitive context instead
of raw request objects or credentials. Credentials should not be embedded in URI paths because a
path is intentionally retained for request diagnostics.

Benchmark file logging is opt-in and does not control development-only benchmark response diagnostics or the
HTML comment setting.
