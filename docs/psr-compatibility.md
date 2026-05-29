# PSR Compatibility

The framework uses PSR interfaces as runtime boundaries for core services and integration points.

## Supported contracts

Supported PSR contracts include:

- PSR-3 logger
- PSR-6 cache
- PSR-7 HTTP messages
- PSR-11 container
- PSR-15 middleware and request handlers
- PSR-17 HTTP factories
- PSR-18 HTTP client integrations

## Required PSR packages

The required runtime dependencies include the relevant PSR packages:

```text
psr/cache
psr/container
psr/http-client
psr/http-factory
psr/http-message
psr/http-server-handler
psr/http-server-middleware
psr/log
```

The framework ships with Nyholm PSR-7 and PSR-17 compatible HTTP message/factory support through:

```text
nyholm/psr7
nyholm/psr7-server
```

## Optional HTTP clients

PSR-18 HTTP client support is provider-based and optional. Depending on the selected integration, an application can use:

```text
guzzlehttp/guzzle
symfony/http-client
php-http/curl-client
php-http/discovery
```

This makes framework boundaries explicit and interoperable with existing PHP packages while keeping framework-specific code behind service providers, middleware, controllers and infrastructure modules.
