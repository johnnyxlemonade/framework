# API Flow

API execution uses the same application context, container, configuration system and provider model as HTTP execution.

The framework API layer provides health checks, generated OpenAPI documentation, simple human-readable docs and application-defined API endpoints registered through endpoint providers.

## Flow

```text
HTTP request
-> ApplicationContextFactory::fromGlobals()
-> KernelFactory::create()
   -> create Framework with core services and framework config defaults
-> Kernel::handle($request)
   -> load application config definitions and apply runtime app values
   -> build the provider plan
   -> register core and HTTP providers
   -> register common framework providers, including ApiServiceProvider
   -> register application providers
   -> boot all registered providers
-> ApiServiceProvider
   -> bind API configuration, canonical mutable state and interface contracts during register()
   -> leave stateless concrete API services to transient container autowiring
   -> during HTTP boot, add API authorization before OPTIONS handling
   -> register framework and application endpoints in the canonical ApiEndpointRegistry
   -> register API routes under configured ApiConfig::prefix()
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

The HTTP error middleware depends only on the optional HTTP
`ExceptionResponseResolverInterface`. `ApiServiceProvider` aliases that contract to the transient
`ApiExceptionResponseResolver`, whose dependencies use the canonical API endpoint resolver and
Problem Details factory. Without the API provider, the HTTP runtime has no API bindings and retains
its HTML error fallback.

`ApiServiceProvider::boot()` contributes authorization middleware and routes only in an HTTP
runtime with API enabled. CLI boot still validates configured endpoint provider classes, but it does
not mutate an HTTP middleware stack or register HTTP routes.

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
Each endpoint declares a `ControllerAction` explicitly, using the same action model as HTTP
routing. String handlers such as `Controller@action` are not supported.

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Routing\ControllerAction;

final class AppApiEndpointProvider implements ApiEndpointProviderInterface
{
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get(
            path: '/app/ping',
            controllerAction: ControllerAction::for(AppPingController::class, 'show'),
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
    controllerAction: ControllerAction::for(AppPrivateStatusController::class, 'show'),
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
- `debug_only` endpoints are available only when debug rules allow them and otherwise use normal authentication and scope checks
- `internal_only` endpoints require an identity explicitly marked with `ApiIdentityTrust::Internal` by the authenticator and then apply normal scope checks

The built-in static bearer authenticator always creates an external identity. This remains true for
a token with the `api:admin` scope: that scope may satisfy endpoint scope requirements, but it never
crosses the internal trust boundary. When no application authenticator establishes an internal
identity, `internal_only` endpoints fail closed.

Identity type names and scopes do not establish trust. An application authenticator may assign
`ApiIdentityTrust::Internal` only after it cryptographically verifies a service credential, for
example a short-lived signed token with the expected issuer and audience or an authenticated mTLS
principal supplied through a trusted server integration. Client IP addresses, localhost and
client-controlled HTTP headers are not sufficient evidence of internal identity.

## Application-provided internal authentication

Applications keep the `ApiAuthenticatorInterface` contract and replace its container binding in an
application service provider. The credential verifier in this example is application-owned; it
must validate the credential before the authenticator assigns internal trust.

```php
use App\Security\ServiceCredentialVerifier;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\ApiIdentity;
use Lemonade\Framework\Api\Security\ApiIdentityTrust;
use Psr\Http\Message\ServerRequestInterface;

final class AppApiAuthenticator implements ApiAuthenticatorInterface
{
    public function __construct(
        private readonly ServiceCredentialVerifier $credentials,
    ) {
    }

    public function authenticate(ServerRequestInterface $request): ?ApiIdentity
    {
        $credential = $this->credentials->verify($request);
        if ($credential === null) {
            return null;
        }

        return new ApiIdentity(
            id: $credential->subject,
            type: 'service',
            scopes: $credential->scopes,
            name: $credential->name,
            trust: ApiIdentityTrust::Internal,
        );
    }
}
```

Bind it from an application provider, which runs after the framework API provider:

```php
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class ApiSecurityServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ApiAuthenticatorInterface::class, AppApiAuthenticator::class);
    }
}
```

The generated OpenAPI operation for an internal endpoint includes
`x-lemonade-access: internal_only`. The bearer security entry describes the credential transport;
the application authenticator remains responsible for deciding whether the verified credential is
internal.

## Opening framework API docs

```bash
curl https://example.test/api/framework/health
curl https://example.test/api/framework/openapi.json
```

Human-readable docs, when enabled:

```text
https://example.test/api/framework/docs
```
