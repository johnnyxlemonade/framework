# Observability

HTTP diagnostics use one shared HTTP interval and SQL aggregation pipeline. They are
**disabled by default**, independently of environment and debug mode. To expose the
four performance headers, list `HttpDiagnostics` in the HTTP section of the application's
`Config.yaml` manifest and create `HttpDiagnostics.yaml`:

```yaml
module: http_diagnostics
config:
  enabled: true
```

The PHP definition is `HttpDiagnosticsConfigDefinition::create()->enabled()`.
`HttpDiagnosticsConfig::enabled()` is the explicit runtime policy. `HttpDiagnosticsHeaders`
adds `X-Lemonade-Time-Ms`, `X-Lemonade-PHP-Peak-Bytes`, `X-Lemonade-SQL-Count` and
`X-Lemonade-SQL-Time-Ms`. Existing cache policy is preserved. It never adds HTML comments or
reads or changes the response body. Enabling these headers also works in production.

`HttpInstrumentation` publishes one immutable `HttpMetrics` after scope cleanup and error
conversion. `DatabaseInstrumentation` supplies the same SQL totals to every consumer.
See [HTTP diagnostics](http-diagnostics-foundation.md) for exact HTTP and driver measurement
boundaries, failure handling, observer contracts and migration guidance.

## Optional Profiler

`johnnyxlemonade/profiler` is a separate development dependency. It supplies bounded request
history, SQL details, rendering spans and an optional HTML toolbar. The framework itself
keeps no history or query list. Diagnostic headers do not require this package.

Add `Lemonade\Profiler\ProfilerServiceProvider` last in `app/Config/Providers.yaml` and
keep `Providers` in `Config.yaml`. That single explicit registration enables capture before
provider bootstrap as well as UI and renderer integration. The entrypoint stays unchanged.
The provider enforces development-only operation. Remove its YAML entry when the optional
package is not installed; a configured missing provider is an error. Configuration loading
precedes activation, but its elapsed time remains included in the shared HTTP duration.
See the Profiler README and [factory boundaries](http-diagnostics-foundation.md#profiler-contracts).

## Logging

Logging is registered early during kernel bootstrap so HTTP handling, CLI failures and kernel exceptions can use the configured logger. Application code should depend on `Psr\Log\LoggerInterface` when it needs application logging.

The framework owns the built-in channel convention and file location:

- `storage/writable/logs/app-YYYY-MM-DD.log`
- `storage/writable/logs/error-YYYY-MM-DD.log`
- `storage/writable/logs/request-YYYY-MM-DD.log`

The path resolver keeps this directory inside application storage and outside the configured public web root.
It rejects traversal, stream-wrapper paths, and a symbolic log directory. The logger creates or repairs the
dedicated `storage/writable/logs` directory to mode `0700` without changing the permissions of the general
application storage root or its other children. Daily log files encountered during append or rotation are
restricted to `0600`. If these boundaries or permissions cannot be enforced, logging fails with a
`LogStorageException` instead of falling back to a less private location.

Appending rejects a symbolic target, confirms that the opened handle is a regular file, and uses one exclusive
file lock. Rotation considers only exact daily filenames and uses non-blocking locks for expired files, so it
skips a file still used by another writer and considers it again on a later rotation pass. Deployments must keep
application storage on a local filesystem with reliable advisory locks and grant the PHP process control of its
private log directory.

All built-in channels use one retention period, with a framework default of seven days. Applications configure policy rather than filenames, sinks or per-channel rotation:

```yaml
module: logging
config:
  retention_days: 7

  request:
    enabled: false
    min_status: 0
```

`app` and `error` are always available. `error` is reserved for runtime and application exceptions, so ordinary HTTP 404 responses are not written there. Request logging is opt-in; when enabled, `min_status: 0` records every response, `400` records client and server failures, and `500` records server failures. This makes a 404 observable in `request.log` only when request logging is enabled and its threshold includes 404.

Framework-generated HTTP request and error records use a fixed metadata allowlist.
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

`HttpRequestLogger` uses the completed HTTP duration, rounded to integer `duration_ms`.
Request logging alone does not activate SQL capture or expose diagnostic headers. It includes
health responses and outer-kernel error responses under the same status and privacy policy.
