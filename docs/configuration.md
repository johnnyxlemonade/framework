# Configuration

Application configuration is YAML-first. YAML is the recommended app-level input format, but it is not the framework's internal config model.

Each application YAML file is loaded as an input document, mapped to a concrete typed `ConfigDefinitionInterface` implementation, and then resolved through the existing typed resolvers into runtime DTO/config objects.

Framework defaults may still remain in PHP internally. Application config loading itself is YAML-only.

The framework resolves application configuration from the application context. By convention, configuration files are stored in:

```text
app/Config/
```

## HTTP configuration files

The HTTP kernel conventionally loads these configuration files:

```text
app/Config/Config.yaml
app/Config/App.yaml
app/Config/Localization.yaml
app/Config/Cache.yaml
app/Config/Logging.yaml
app/Config/Session.yaml
app/Config/Database.yaml
app/Config/Breadcrumbs.yaml
app/Config/Upload.yaml
app/Config/Providers.yaml
```

The HTTP kernel loads routes separately after providers have been registered:

```text
app/Config/Routing.php
```

## CLI configuration files

The CLI kernel conventionally loads the same application configuration files as the HTTP kernel, except routing, and additionally loads:

```text
app/Config/Commands.yaml
```

## Config manifest

The application config manifest is itself YAML and lists logical config file aliases:

```yaml
shared:
  - App
  - Api
  - Providers
http:
  - HtmlMinify
cli:
  - Commands
```

Each listed file is resolved by convention to `.yaml` / `.yml`.

Every loaded application config file must still end up as an implementation of `ConfigDefinitionInterface`. Raw array config files are rejected as an internal runtime contract.

## Runtime application values

Runtime application values are injected from the current application context:

```text
app.base_path
app.env
app.debug
app.app_path
app.config_path
app.storage_path
```

## Environment helper

Environment values can be read through `Lemonade\Framework\Support\Env`. The helper resolves values from `$_ENV`, then `$_SERVER`, then `getenv()`.

In YAML app config, environment-backed values are expressed through explicit directives:

```yaml
module: app
config:
  base_url:
    $env: APP_BASE_URL
    type: string
    default: http://localhost
```

```yaml
module: api
config:
  enabled: true
  prefix: /api
  endpoint_providers:
    - App\Api\AppApiEndpointProvider
  security:
    static_bearer:
      enabled: true
      token:
        $env: API_TOKEN
        type: string
      scopes:
        - api:admin
        - openapi:read
  framework:
    docs:
      enabled: true
```

`Env::list()` expects a comma-separated string and returns a normalized list of unique, non-empty values.

```bash
APP_SUPPORTED_LOCALES=cs,en,de
```

Result:

```php
['cs', 'en', 'de']
```

Routing is the intentional exception: application routes still live in `app/Config/Routing.php`.

## Compiled application config cache

The framework can compile the resolved application config definitions into generated PHP cache files. YAML and environment values remain the source of truth. The generated cache is only a derived artifact.

### Where the cache is stored

The cache is written under the application storage cache directory resolved from `ApplicationContext`:

```text
storage/cache/framework/config/
```

Generated filenames are entrypoint-specific:

```text
storage/cache/framework/config/application-http.php
storage/cache/framework/config/application-cli.php
```

HTTP and CLI therefore use separate cache artifacts.

### What the cache contains

The generated PHP file returns a structured payload with:

- cache format version
- target entrypoint (`http` or `cli`)
- tracked source files
  - relative path
  - whether the file existed when the cache was built
  - SHA-1 hash for existing files
- tracked environment values actually used during config resolution
- serialized typed `ConfigDefinitionInterface` payloads

The cache does not store live services, container state, providers, routes, or middleware.

### When the cache is active

Compiled config cache activation is currently driven only by the application environment:

- `dev` / `development` / `local` -> uncached config load
- `test` / `testing` -> uncached config load
- `prod` / `production` and any other unrecognized environment string -> compiled config cache path

There is no separate config-cache feature switch and no app-config switch for this behavior.

Framework environments are defined by `Environment::fromString()`:

- `dev`, `development`, `local` -> development
- `test`, `testing` -> testing
- everything else -> production

That means a deployment environment such as `staging` is not a separate framework mode today. It is treated as production and therefore uses compiled config cache.

### Cache miss, hit, rebuild

Current runtime lifecycle:

```text
request or CLI command
-> ConfigLoader::loadApplication(...)
-> if environment is not production:
   -> load YAML / PHP config sources directly
-> if environment is production:
   -> try ApplicationConfigCache::loadIfFresh(...)
   -> on cache hit: hydrate typed config definitions from generated PHP
   -> on cache miss: load sources directly, then write fresh compiled cache
```

In production, the normal deployment behavior today is:

```text
deploy code / config / env
-> first HTTP request or first CLI command reaches ConfigLoader
-> cache miss is detected
-> current config is loaded from source
-> fresh compiled cache is written
-> later requests or commands reuse the compiled cache while it stays fresh
```

There is no dedicated prebuild command yet. Rebuild today is therefore lazy and automatic: delete or invalidate the generated file, then let the next production HTTP/CLI bootstrap recreate it.

### Freshness and invalidation

The generated cache is accepted only when all of the following still match:

- cache payload version
- target entrypoint
- source file existence state
- source file SHA-1 hashes
- environment values that were actually read during config resolution

This means:

- changing a tracked YAML file invalidates the cache
- changing a tracked `ConfigMap.php` file invalidates the cache
- changing a used environment value invalidates the cache
- adding a config file that is already listed in `Config.yaml` invalidates the cache because a previously missing tracked candidate changes from `exists: false` to `exists: true`
- removing a previously existing tracked config file invalidates the cache for the same reason
- changing `Config.yaml` invalidates the cache because the manifest itself is tracked

The cache does not rebuild when:

- source files are unchanged
- used environment values are unchanged
- unrelated environment variables change
- files not referenced by the current config manifest / loader path change

### Source tracking details

`ConfigLoader` tracks:

- `app/Config/Config.yaml` or `Config.yml`
- every candidate config source path resolved from the manifest
- every actual loaded YAML file
- each YAML directory `ConfigMap.php` used during loading

For environment tracking, the loader stores only environment keys that were actually consumed by YAML `$env` resolution. Unused environment variables are not part of cache freshness.

### Corrupted or invalid cache artifacts

If the generated cache file:

- does not exist
- returns a payload with the wrong version
- has the wrong entrypoint
- has invalid payload structure
- references source metadata that no longer matches
- references tracked environment values that no longer match

then `ApplicationConfigCache::loadIfFresh()` returns `null` and runtime falls back to the normal source-loading path. A fresh cache is then written in production.

### Write behavior and concurrency

The cache writer generates PHP content into a temporary file next to the target file and then renames it into place.

Current implementation details:

- target file is `...application-http.php` or `...application-cli.php`
- temporary file is the same path with `.tmp` suffix
- if an old target exists, it is unlinked before rename

This keeps ordinary single-process writes recoverable and avoids partial file contents in the final target.

There is currently no file lock or cross-request coordination. Under concurrent production cache misses, multiple requests may rebuild the same cache independently. That can cause duplicated rebuild work and a brief gap between unlink and rename, but the runtime still re-validates freshness on every load and falls back safely to source loading on miss.

### Source of truth and safe manual operations

YAML files and environment values are authoritative. Generated cache files:

- are not source of truth
- should not be edited manually
- do not need to be committed
- are safe to delete

Manual clear today means removing only the framework-owned generated files:

```text
storage/cache/framework/config/application-http.php
storage/cache/framework/config/application-cli.php
storage/cache/framework/config/application-http.php.tmp
storage/cache/framework/config/application-cli.php.tmp
```

After manual clear, the next production HTTP request or CLI command rebuilds the corresponding artifact automatically.

### Practical workflow

Development / testing:

```text
edit YAML or env
-> run app / tests
-> config is always reloaded from source
-> no compiled config cache is required
```

Production deployment:

```text
deploy code
-> deploy YAML config
-> deploy environment values
-> first production HTTP request or CLI command detects miss or invalid cache
-> runtime rebuilds compiled config automatically
-> later runs use the compiled cache
```

Changing YAML in production:

```text
update app/Config/*.yaml
-> tracked SHA-1 no longer matches
-> next production bootstrap ignores old cache
-> runtime rebuilds cache from the updated YAML
```

Changing an environment value in production:

```text
update environment value actually used by YAML $env
-> tracked env metadata no longer matches
-> next production bootstrap ignores old cache
-> runtime rebuilds cache using the new env value
```

Manual clear / rebuild:

```text
delete framework-owned config cache files
-> next production HTTP request rebuilds HTTP cache
-> next production CLI command rebuilds CLI cache
```

## Health fast path note

The framework health fast path can reuse the same compiled HTTP config cache when it is fresh, but compiled config cache is not required for the health endpoint to exist.

Current behavior:

- production health fast path prefers compiled HTTP config cache when valid
- non-production health fast path uses lightweight config-only resolution without full provider/bootstrap lifecycle
- protected or disabled health configuration falls back to the normal HTTP lifecycle

The health endpoint contract therefore stays the same across environments even though the config lookup path is lighter than a full HTTP bootstrap.
