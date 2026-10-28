# HTTP Request Flow

Lemonade Framework uses a deliberately linear HTTP lifecycle. The request enters the kernel, configuration is loaded, services are registered, middleware is executed, routing resolves a controller action and the action result is normalized into a PSR response.

## Flow

```text
public/index.php
-> ApplicationContextFactory::fromGlobals()
-> KernelFactory::create()
-> Kernel::handle()
   -> enter the shared HTTP lifecycle
or Kernel::run($request)
   -> enter the same shared HTTP lifecycle
-> shared HTTP lifecycle
   -> start the shared HttpInstrumentation boundary
   -> create ServerRequest from globals when no request is provided
   -> create an explicitly supplied early diagnostics observer, if any
   -> load HTTP config definitions once into the root config registry
   -> otherwise activate an observer factory from explicitly configured application providers
   -> create a Request scope
   -> bind the provided ServerRequestInterface only in that scope
   -> try the framework health fast-path
      -> on a public health hit, create and return the response without full bootstrap
-> Kernel::bootstrap()
   -> apply runtime app config
   -> register core providers
   -> register HTTP provider
   -> register common framework providers
   -> register application providers
   -> load routes
   -> resolve tagged RouteRegistrarInterface services
   -> finalize and freeze routing
-> Framework::runInScope()
   -> dispatch within the Kernel-owned instrumentation boundary
   -> resolve global middleware stack
   -> execute PSR-15 middleware pipeline
   -> DispatchRequestHandler
      -> match route
      -> attach immutable RouteMatch to the request
      -> create controller request handler
      -> resolve route-specific middleware
      -> execute route middleware pipeline
      -> resolve controller
      -> resolve the current scope-bound ServerRequestInterface
      -> resolve action arguments
      -> call controller action
      -> normalize result to PSR response
-> close the request scope and convert escaping errors
-> complete HttpMetrics, publish diagnostics and detach observers
-> ResponseEmitter for Kernel::handle()
```

## Entrypoint example

```php
<?php

use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\KernelFactory;

require __DIR__ . '/../vendor/autoload.php';

$context = (new ApplicationContextFactory())->fromGlobals(
    dirname(__DIR__),
);

$kernel = (new KernelFactory())->create($context);
$kernel->handle();
```

## Notes

Each `Kernel::run()` call creates a `Request` scope and binds its exact
`ServerRequestInterface` only in that scope. Bootstrap and application-provider registration
remain requestless: the root container never stores a request-specific binding. The scope is closed
in a `finally` block after a normal response, a not-found response, or any middleware/controller
exception. This also applies to the health fast path.

`HttpInstrumentation` owns one HTTP interval and shares `DatabaseInstrumentation` with root-owned
connections. `Kernel::run()` and `Kernel::handle()` use the same boundary, including health and
error responses. `Framework::run()` owns the boundary when used directly; `Framework::runInScope()`
dispatches in the caller's scope without starting another interval. Logging, header formatting,
Profiler publication and response emission are outside the measured interval. See
[HTTP diagnostics](http-diagnostics-foundation.md) for consumer activation and SQL boundaries.

Configuration definitions are loaded before the Request scope is created. The health fast-path runs
after the request is bound locally but before `Kernel::bootstrap()`: a matching public health request
therefore skips full provider registration, route loading, middleware and controller dispatch. A
health miss continues with the ordinary bootstrap and request pipeline.

Services resolved for the request pipeline, route middleware, dispatch handler and controller use
the active scoped container. Therefore `ContainerInterface` injected into a request-scoped or
transient runtime service resolves to that `ScopedContainerInterface`; root singletons remain shared
and cannot consume request-local values.

`Responses` is a root-safe singleton facade over the PSR-17 response and stream factories. Plain
controllers can inject it for HTML, text, JSON, redirect, download and stream responses; view
controllers can inject the request-scoped `ViewRendererInterface` for rendered HTML responses.

`ResponseEmitter` emits normal readable PSR-7 bodies in 64 KiB chunks rather than casting a
body to a string. This keeps framework memory bounded by the configured read chunk and any current
body chunk. File downloads therefore retain their file-backed body and known `Content-Length` when
available. Stream producers yield string chunks lazily and have no inferred `Content-Length`.
Network delivery may still be buffered by PHP, a web server, or a proxy. After emission of the
first body chunk, a producer failure cannot be reliably replaced with a new status or error body.
Producer failures occur during emission, outside the kernel response conversion path; before the
first body chunk, only an outer integration can decide how to handle such a failure.

This is a breaking change from older versions that bound `ServerRequestInterface` into the root
container before provider registration. Providers must not inspect the current request in
`register()` or `boot()`; use middleware, a scoped service, or a controller for request-dependent
decisions. Applications using the former provider-request pattern must migrate that composition
logic separately; the framework provides no root-binding compatibility shim.

`Framework::runInScope()` is the kernel integration API. It accepts only a `Request` scope whose
scope-local `ServerRequestInterface` binding is the identical object passed as its request argument.
The guard rejects command/job scopes, a missing local request binding, and a mismatched request.

Bootstrap happens before request dispatch. Global middleware wraps route matching and controller execution. Route-specific middleware wraps the matched controller handler.

For ordinary web routes, `DispatchRequestHandler` adds the immutable `RouteMatch` as the
`Lemonade\Framework\Routing\RouteRequestAttributes::MATCH` PSR-7 request attribute. For registered
API routes, authorization obtains that match from the same `Router` earlier and the dispatch
handler reuses the identical object after successful authorization. Middleware before API
authorization must treat the attribute as absent; route middleware and the controller receive the
same matched request instance. A non-matched request never reaches a controller with a route-match
attribute.

Controller actions may return a PSR response directly. Scalar, stringable and `null` return values are normalized into HTML responses.

## Trusted proxy policy

The `http_server` module defines a fail-closed policy for resolving client and external-origin
metadata. No proxy or request-derived host is trusted by default. Exact IPv4 and IPv6 addresses and
CIDR networks may be configured as immediate trusted peers, together with exactly one supported
forwarding-header family.

```yaml
module: http_server
config:
  trusted_proxies:
    - 10.0.0.10
    - 172.20.0.0/16
    - 2001:db8:100::/64
  forwarded_header_profile: x_forwarded # none, forwarded, x_forwarded
  trust_forwarded_prefix: false
  allowed_hosts:
    - example.com
    - '*.example.com'
```

`TrustedProxyMatcher` performs address matching without DNS resolution. `RequestContextResolver`
accepts an explicit PSR server request and produces an immutable `ResolvedRequestContext`; it never
reads global `$_SERVER` state. Forwarding metadata is ignored unless the immediate `REMOTE_ADDR` is
trusted. Accepted chains are traversed from right to left and stop at the first untrusted hop.
Every configured proxy must replace or sanitize the selected forwarding-header family before it
passes a request to the application. When prefix trust is enabled, that obligation also covers
`X-Forwarded-Prefix`.

For `X-Forwarded-*`, origin fields may contain one authoritative value replaced by the immediate
trusted proxy, or a list whose length is exactly aligned with `X-Forwarded-For`. Other list lengths
are rejected as ambiguous.

`Forwarded` and `X-Forwarded-*` origin metadata cannot be combined. Invalid or ambiguous trusted
metadata fails resolution, while malformed forwarding metadata from an untrusted peer is ignored.
An empty `allowed_hosts` list approves no request-derived host, and a wildcard matches only proper
subdomains at a DNS-label boundary.

The kernel resolves and request-scope binds this context before the health fast-path, middleware,
sessions, controller dispatch, and request logging. Invalid trusted metadata produces a generic
`400 Bad Request`, including in debug mode. `HttpRequestInspector`, `ServerHelper`, `ServerBag`,
`BaseUrlResolver`, session cookies, and reCAPTCHA client-IP submission consume that same context and
do not parse proxy headers independently.

When `app.base_url` is configured, it must be an absolute `http://` or `https://` URL and its path is
the complete application prefix; a forwarded prefix is not appended. Without `app.base_url`,
absolute URLs can be generated only when the resolved request host matches `allowed_hosts`.

### Direct localhost

No `http_server` configuration is required. Direct server metadata supplies the request scheme and
peer address, forwarded headers remain ignored, and route and asset helpers return relative URLs.

Configure hosts only when the application intentionally derives absolute URLs from the request
instead of defining `app.base_url`:

```yaml
module: http_server
config:
  forwarded_header_profile: none
  allowed_hosts:
    - localhost
    - 127.0.0.1
    - ::1
```

### DDEV

DDEV's Apache configuration exposes the router-normalized HTTPS state through standard server
metadata. A normal DDEV application therefore needs no proxy IP discovery and no `http_server`
module. Keep the default forwarded profile `none` and define the canonical application origin when
absolute URLs are required:

```yaml
module: app
config:
  base_url: https://lemonade.ddev.site
```

In this zero-config proxy mode, `clientIp()` intentionally reports the immediate peer visible to
PHP. Recovering the address before Traefik is an advanced deployment choice and still requires an
explicit exact proxy address or narrow CIDR plus one forwarded-header profile. Never compensate for
changing container addresses by trusting all Docker or private networks.

### Production Nginx reverse proxy

Configure only the address or narrow network from which the application actually receives Nginx
connections. Nginx must replace the selected headers rather than pass client-supplied values:

```nginx
proxy_set_header X-Forwarded-For $remote_addr;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Port $server_port;
proxy_set_header X-Forwarded-Prefix /application;
```

```yaml
module: http_server
config:
  trusted_proxies:
    - 10.0.0.10
  forwarded_header_profile: x_forwarded
  trust_forwarded_prefix: true
  allowed_hosts:
    - app.example.com
```

### Production Apache reverse proxy

Apache must likewise unset or replace inbound forwarding headers before proxying. With an Apache
peer at `10.0.0.20`, the framework side is:

```yaml
module: http_server
config:
  trusted_proxies:
    - 10.0.0.20
  forwarded_header_profile: forwarded
  trust_forwarded_prefix: false
  allowed_hosts:
    - app.example.com
```

The Apache virtual host must emit one sanitized `Forwarded` chain containing literal `for`, `proto`,
and `host` values. A single-proxy TLS virtual host can replace inbound forwarding metadata with:

```apache
RequestHeader unset X-Forwarded-For early
RequestHeader unset X-Forwarded-Proto early
RequestHeader unset X-Forwarded-Host early
RequestHeader set Forwarded "expr=for=%{REMOTE_ADDR};proto=https;host=app.example.com"
```

Do not enable both header profiles and do not use the mere presence of a header, localhost, Docker
networking, or a private address as proof of proxy trust.

The `HttpServer` file must be listed in the HTTP section of the application `Config.yaml` manifest
when an application supplies these settings.

### Compatibility changes

- `HttpRequestInspector::clientIp()` now returns the first address outside the verified trusted
  proxy chain and no longer prefers public addresses or consumes `Client-IP` variants.
- `ServerHelper` is a request-scoped instance instead of a static global helper. `ServerBag` now
  accepts that helper and delegates trusted host, scheme, and client-IP access to it.
- A configured `app.base_url` without an explicit HTTP scheme is invalid at bootstrap.
- Request-derived absolute URL generation no longer falls back to `localhost`, `SERVER_NAME`, or an
  unapproved Host header.
- View assets and public upload images are root-relative and do not require `app.base_url` or an
  allowlisted request host. Explicit absolute URL operations remain fail-closed.
- Services that generate request-derived public URLs are request-scoped so context cannot leak
  between sequential requests. CLI use continues to work when `app.base_url` is configured.

## HTTP statuses

Framework response paths use the general `Lemonade\Framework\Http\HttpStatus` backed enum
for named HTTP statuses. PSR-7 response factories still receive the compatible integer through
`HttpStatus::CASE->value`. The enum is a framework HTTP contract and does not contain
application or administration-specific error codes.
