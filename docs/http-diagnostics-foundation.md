# HTTP diagnostics

The HTTP runtime and database drivers use one instrumentation path. The former Benchmark
subsystem has been removed completely; there are no compatibility aliases or runtime adapters.

## Ownership and completion

`Kernel::run()` and `Kernel::handle()` enter the same internal `HttpInstrumentation`
boundary. It records one `hrtime(true)` origin before request construction and
configuration loading. Kernel owns request-scope creation and closes it in `finally`.
The boundary completes after bootstrap, dispatch, middleware unwinding, scope cleanup
and conversion of escaping errors into HTTP responses. Health responses use exactly
the same boundary, even when full provider bootstrap is skipped.

`Framework::run()` uses the same boundary around its standalone scope. Its existing
exception propagation contract is preserved: an unconverted exception still escapes,
a completed observer receives metrics, and instrumentation is detached. There is no
response publication callback when no response exists. `Framework::runInScope()`
dispatches in the caller's scope and does not start another measurement. Manual work
performed before calling either entrypoint, explicit earlier `Kernel::bootstrap()`,
Composer autoload, response emission and network transfer are outside the interval.

A real consumer is an explicit HTTP exposure configuration, an active profiler
observer, or enabled request logging. Without any consumer there is no summary,
end-clock read, peak-memory sample or SQL timing. One provisional monotonic origin
is read because configuration may enable measurement during bootstrap. Logging alone
measures HTTP duration without enabling SQL capture. Checkpoints read the clock only
with an active observer.

One immutable `Observability\Http\HttpMetrics` contains only `durationMs`,
`phpPeakMemoryBytes`, `sqlCount`, `sqlMs` as private readonly values, exposed through
`durationMs()`, `phpPeakMemoryBytes()`, `sqlCount()` and `sqlMs()`. Every consumer receives these same completed
values; none recomputes duration or SQL totals. Request logging rounds this duration
to its existing integer `duration_ms` format. Publication, request-log writing,
profiler storage, toolbar decoration and diagnostic-header formatting happen after
completion and are excluded. Profiler failures cannot replace application responses.

PHP peak memory uses `memory_get_peak_usage(false)`: this is the PHP script/process
peak, not a reset request-local peak in persistent workers. No PHP peak counter is
reset. Sequential requests get fresh SQL totals and observers; nested or concurrent
requests on one runtime are unsupported and rejected. Every exit releases capture
state. No timeline, query list or metrics history is retained by the framework.

## Explicit HTTP headers

Load `HttpDiagnostics.yaml` through the application's `Config.yaml` manifest:

```yaml
module: http_diagnostics
config:
  enabled: true
```

The typed equivalent is `HttpDiagnosticsConfigDefinition::create()->enabled()`.
`HttpDiagnosticsConfig` is immutable with a private setting and `enabled()` getter.
The default is false; neither activation nor presentation consults `APP_ENV` or
`APP_DEBUG`. Enabling it exposes performance data to the client, including production.

`HttpDiagnosticsHeaders` adds only these diagnostic fields:

- `X-Lemonade-Time-Ms`
- `X-Lemonade-PHP-Peak-Bytes`
- `X-Lemonade-SQL-Count`
- `X-Lemonade-SQL-Time-Ms`

Times have three decimal places independently of locale. No other headers are changed;
cache policy remains owned by the application. Cached responses may contain cached measurements. The helper never
reads or modifies the body or its stream position; status and representation metadata
remain intact. There is no HTML comment. Disabled presentation returns the exact
response object. Profiler's optional development toolbar is a separate consumer.

## Shared SQL instrumentation

One root `Database\Instrumentation\Internal\DatabaseInstrumentation` is shared by
`ConnectionFactory`, PDO, MySQLi and ODBC. Directly constructed connections can receive
that instance explicitly; their optional default instrument remains inactive.
`DatabaseInstrumentation::collect()` holds fresh `DatabaseMeasurements` only for the
lexical operation and releases both totals and observer in `finally`.

SQL collection starts after configuration for a YAML-configured profiler or header-only
exposure, before provider bootstrap and health dispatch. An explicitly supplied early
factory can start it before configuration. Configuration files should declare configuration, not execute SQL.
Request logging alone does not activate SQL. Two scalar aggregates track attempted
queries and unrounded duration. SQL, bindings, results and database exceptions are
never retained in the shared instrument.

Outside collection, `query()` invokes the operation without a clock read. Inside,
it uses two `hrtime(true)` reads, adds one attempt in `finally`, and optionally sends
that same duration plus raw SQL and success to `DatabaseObserverInterface`. Failures
are counted once and retain their original driver exception conversion. An observer
failure disables that observer for the remaining capture without replacing query
results or database exceptions. Query details belong solely to the bounded, sanitizing
Profiler. Connection setup is timed only for a database observer and never contributes
to SQL count or duration. Observer callback time is excluded from each SQL interval.

### Preserved driver boundaries

| Driver | Measured SQL operation | Exclusions |
| --- | --- | --- |
| PDO | `execute()`, after `pdo()`: prepare, bind, execute, row-count bookkeeping and error conversion | Lazy connection open, later fetch and cursor iteration |
| MySQLi | `execute()`, after `mysqli()`: direct or prepared execution, result retrieval and execution-result bookkeeping | Lazy connection/session setup, later row fetching |
| ODBC | `executeStatement()`, after `resource()`: normalize bindings, prepare/execute or direct execution and error handling | Connection open, later row fetching |

The original SQL text is sent for ODBC. A failed connection is not a SQL attempt.
Cached handles cause no new connection sample. Native transaction calls, connection
session setup and operations bypassing framework APIs through raw handles do not
become additional SQL counts. Existing cursor consumption and cleanup behavior is
preserved; an unconsumed generator does not execute or measure a query.

## Profiler contracts

Ordinary Profiler integration uses only the standard `Providers.yaml`, referenced as
`Providers` in `Config.yaml`:

```yaml
module: providers
config:
  providers:
    # Keep existing providers here, in their original order.
    - Lemonade\Profiler\ProfilerServiceProvider
```

The HTTP boundary examines only these explicitly configured application providers for
`HttpDiagnosticsObserverFactoryInterface` after configuration loads. It creates the first
active observer before health dispatch and provider bootstrap, using `ProviderFactory` and
the original monotonic origin. The provider also registers its UI and renderer services in
the ordinary provider lifecycle. The factory constructor and `create()` must be bootstrap-safe;
constructor dependencies must already exist as explicit root bindings. Factories must not rely
on constructor side effects or instance identity shared with later provider registration.
There is no Composer discovery, environment-based provider list or optional-class fallback.
A configured missing provider is a configuration error, including on the health path.
Omitting the provider disables this integration; the installed package alone does nothing.
The Profiler itself returns no observer and registers no runtime services outside development.
Remove its YAML entry when deploying without the optional package.

Configuration must be read before its provider list is known. Consequently YAML integration
cannot observe SQL or checkpoints emitted while loading configuration, nor configuration
failures before activation. HTTP duration still includes that time through the common origin.
It captures provider bootstrap SQL, routes, health and dispatch. No missing configuration
checkpoint is synthesized. Explicit earlier `Kernel::bootstrap()` remains outside the interval.

For exceptional integrations needing pre-configuration observation, the existing explicit
`KernelFactory(diagnostics: ...)` or standalone `Framework(..., diagnostics: ...)` argument
remains supported and takes precedence over configured factories. Ordinary Profiler use
requires no custom entrypoint PHP. `Framework::run()` can use providers already supplied through
its typed configuration, but does not load an application's YAML or register providers itself.

`HttpDiagnosticsObserverInterface` receives real typed checkpoints on the common
monotonic origin, then `completed(HttpMetrics)`, then `response(request, response,
matchedRoute)` for persistence and optional decoration. Missing completion checkpoints
mean an operation failed or was skipped; warmed bootstrap does not emit fake stages.
The request scope is already closed at publication. An observer may also implement
`DatabaseObserverInterface` for individual SQL and connection samples. Its factory
receives the common origin so renderer spans share the HTTP timeline origin.

Profiler activation is development-only and independent of diagnostic headers.
Custom `ProfilerConfig` must be bound before the first request, not by a later
bootstrap provider. Default Profiler configuration requires no additional binding.

## Migration from the former Benchmark

This is a breaking API removal. Delete the former subsystem's imports, service registrations,
configuration files and manifest entries from applications. Its run coordinator, run objects,
middleware, response injector, SQL observer and configuration types no longer exist. There are
no deprecated aliases, wrappers or forwarding calls.

- Ordinary Kernel factories need no diagnostics configuration. For simple response metrics,
  explicitly configure `http_diagnostics.enabled`; its default is false. Replace consumers of
  the former diagnostic headers with the four fields listed above. HTML-comment output has
  no replacement.
- Install `johnnyxlemonade/profiler` as an optional development dependency for history, toolbar
  and rendering measurements. Upgrade it together with the framework and add its provider last in
  `Providers.yaml`. Omit that entry in deployments that do not install the package.
- Manual connection and `ConnectionFactory` construction now takes `DatabaseInstrumentation`
  in place of the former measurement dependency. The query-detail capture constructor flag
  has been removed. Detailed SQL consumers implement `DatabaseObserverInterface`; HTTP
  consumers implement the HTTP observer/factory contracts described above.
- Dispatch and `ControllerResolver` no longer take a measurement coordinator or require an
  active run. Standalone `Framework::run()` owns its HTTP boundary automatically.
- Request logging is owned by the HTTP boundary through `HttpRequestLogger`. Remove the former
  request-logging middleware from custom stacks. Status filtering and allowlisted metadata
  remain unchanged; health and outer-kernel errors participate as well.
- Logging now has only app, error and optional request channels. Remove the former measurement
  channel binding, enablement option and fourth `LoggingConfig` constructor argument. Use
  request logging for HTTP duration and the Profiler for detailed development inspection.
- CLI creates neither HTTP measurement nor automatic SQL capture.

Integration tests cover bootstrap, middleware return, scope cleanup, normal/error/health
responses, standalone dispatch, repeated requests, logging-only capture, observer failures,
exact response preservation and shared profiler/header totals. PDO SQLite, PDO MySQL and
MySQLi are exercised in DDEV. The ODBC integration case requires ext-odbc and TEST_ODBC_DSN;
it explicitly skips when those facilities are unavailable.
