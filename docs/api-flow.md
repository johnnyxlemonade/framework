# API Flow

API execution uses the same application context, container, configuration system and provider model as HTTP execution.

The framework API layer provides health checks, generated OpenAPI documentation, simple human-readable docs and application-defined API endpoints registered through endpoint providers.

## Flow

```text
HTTP request
-> ApplicationContextFactory::fromGlobals()
-> KernelFactory / Kernel wiring
-> Kernel::handle($request)
   -> bootstrap application
   -> load framework config defaults
   -> load app config overrides
   -> register framework providers
   -> register application providers
   -> register ApiServiceProvider
-> ApiServiceProvider
   -> register ApiEndpointRegistry
   -> register framework API endpoint provider
   -> register app API endpoint providers from ApiConfig
   -> register API routes under configured ApiConfig::prefix
-> API middleware
   -> resolve request path against ApiEndpointRegistry
   -> skip non-API requests
   -> allow public endpoints
   -> authenticate protected endpoints
   -> check endpoint scopes
-> controller / handler
-> PSR-7 response
```

## Error responses

An API error response is selected only for a request that resolves to a registered
`ApiEndpointRegistry` endpoint under the configured API prefix. A generic `/api` path and an
`Accept: application/json` header alone do not change the normal HTTP HTML error policy.

For a registered endpoint, authentication and authorization failures, not-found exceptions and
unexpected handler failures use `application/problem+json`. An unexpected API failure always has a
generic Problem Details body, including in debug mode; exception details remain available through
the framework error log. HTML applications and non-registered requests keep the standard HTML error
responses.

## Framework endpoints

```text
GET /api/framework/health
GET /api/framework/openapi.json
GET /api/framework/docs
```

`/api/framework/health` is a public runtime availability endpoint.

`/api/framework/openapi.json` returns generated OpenAPI 3.1 specification.

`/api/framework/docs` returns simple human-readable API documentation.

OpenAPI output is generated from `ApiEndpointRegistry`, so it includes framework endpoints and application endpoints registered through `ApiEndpointProviderInterface`.

## Configuration

Framework API defaults are defined in `framework/src/Config/Api.php`.

Applications should override them in `app/Config/Api.yaml`.

```yaml
module: api
config:
  enabled: true
  prefix: /api
  endpoint_providers:
    - App\Api\AppApiEndpointProvider
  framework:
    openapi:
      access: public
    docs:
      enabled: true
      access: public
```

Application YAML is still mapped to the typed `ApiConfigDefinition`, then resolved through `ApiConfigResolver` into runtime `ApiConfig`.

Framework defaults are conservative:

- health is public
- OpenAPI is protected by default
- docs are disabled and protected by default

Applications can explicitly make OpenAPI/docs public for local development.

## Endpoint provider example

Application endpoints are not added by editing framework routes. They are registered through endpoint providers.

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;

final class AppApiEndpointProvider implements ApiEndpointProviderInterface
{
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get(
            path: '/app/ping',
            handler: AppPingController::class . '@show',
            name: 'app.ping',
            summary: 'App ping',
            description: 'Returns basic app API availability status.',
            access: ApiAccess::Public,
            metadata: new ApiEndpointMetadata(
                tags: ['App'],
            ),
        );
    }
}
```

Register the provider in `app/Config/Api.yaml`:

```yaml
module: api
config:
  endpoint_providers:
    - App\Api\AppApiEndpointProvider
```

The endpoint will be available under `GET /api/app/ping` and will also appear in generated OpenAPI output.

## Protected endpoint example

```php
$registry->get(
    path: '/app/private-status',
    handler: AppPrivateStatusController::class . '@show',
    name: 'app.private_status',
    summary: 'Private app status',
    description: 'Returns protected app status information.',
    access: ApiAccess::Protected,
    metadata: new ApiEndpointMetadata(
        tags: ['App'],
        scopes: ['api:admin'],
    ),
);
```

Protected endpoints require a bearer token:

```bash
curl -H "Authorization: Bearer $API_TOKEN" https://example.test/api/app/private-status
```

## OpenAPI path rule

OpenAPI output uses API prefix in `servers.url`.

Individual paths are not prefixed with `/api`.

```json
{
  "servers": [
    {
      "url": "https://example.test/api"
    }
  ],
  "paths": {
    "/framework/health": {},
    "/framework/openapi.json": {},
    "/framework/docs": {},
    "/app/ping": {}
  }
}
```

## Access modes

- `public` endpoints do not require bearer token and do not include OpenAPI security metadata
- `protected` endpoints require bearer token and can require scopes
- `debug` endpoints are available only when debug rules allow them

## Opening framework API docs

```bash
curl https://example.test/api/framework/health
curl https://example.test/api/framework/openapi.json
```

Human-readable docs, when enabled:

```text
https://example.test/api/framework/docs
```
