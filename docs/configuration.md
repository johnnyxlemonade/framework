# Configuration

Application configuration is YAML-first. YAML is the recommended app-level input format, but it is not the framework's internal config model.

Each application YAML file is loaded as an input document, mapped to a concrete typed `ConfigDefinitionInterface` implementation, and then resolved through the existing typed resolvers into runtime DTO/config objects. Explicit PHP sources that return a `ConfigDefinitionInterface` are also supported; raw PHP array configuration is not.

Framework defaults may still remain in PHP internally.

The framework resolves application configuration from the application context. By convention, configuration files are stored in:

```text
app/Config/
```

## Configuration files

`app/Config/Config.yaml` (or `.yml`) is an optional manifest for application overrides. When neither
manifest exists, HTTP and CLI bootstrap use the framework's built-in secure defaults without loading
application config files. The manifest's `shared` list is loaded for both entrypoints; the HTTP kernel
then loads `http`, and the CLI kernel loads `cli`. All three sections are optional and omitted sections
behave as empty lists. An empty manifest is therefore valid.

Each list contains explicit file specifications. Extensionless specifications try the exact path,
`.yaml` and `.yml`; specifications with an extension use that exact path, including explicit PHP
definition sources. Once a file is listed, one of its resolved candidates must exist. A missing
explicitly declared file fails bootstrap with a clear error instead of being silently ignored.

A typical HTTP application can use aliases that resolve to files such as:

```text
app/Config/Config.yaml
app/Config/App.yaml
app/Config/Localization.yaml
app/Config/Cache.yaml
app/Config/Logging.yaml
app/Config/Session.yaml
app/Config/Database.yaml
app/Config/Upload.yaml
app/Config/Providers.yaml
```

The HTTP kernel requires and loads routes separately after providers have been registered:

```text
app/Config/Routing.php
```

The CLI manifest commonly adds:

```text
app/Config/Commands.yaml
```

CLI bootstrap also loads `app/Config/Routing.php` when the file exists, so route registrars can be
initialized consistently without making the route file mandatory for console-only applications.

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

The manifest is authoritative; configuration aliases are not inferred from a fixed framework list.
Only `shared`, `http`, and `cli` are accepted as top-level keys, and every present section must be a
YAML list of non-empty file names. Unknown keys, malformed YAML, and invalid section values fail
explicitly. A minimal application does not need to create a manifest at all.

Every loaded application config file must still end up as an implementation of `ConfigDefinitionInterface`. Raw array config files are rejected as an internal runtime contract.

## Runtime application values

Runtime application values are injected from the current application context:

```text
app.base_path
app.public_path
app.env
app.debug
app.app_path
app.config_path
app.storage_path
```

## Application secret

`app.secret` is the canonical stable application secret for framework capabilities
that need a host-provided secret. Configure it from `APP_SECRET`; do not derive it
from a feature-specific secret. Capabilities that require it fail explicitly when
the value is missing or empty.

```yaml
module: app
config:
  secret:
    $env: APP_SECRET
    type: string
    default: ''
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

The framework can compile resolved application config definitions into authenticated, non-executable
data files. YAML sources, explicit PHP sources returning `ConfigDefinitionInterface`, and environment
values remain the source of truth. The generated cache is only a derived artifact and is never loaded
with `require` or `include`.

### Where the cache is stored

The cache is written under the application storage cache directory resolved from `ApplicationContext`:

```text
storage/cache/framework/config/
```

Generated filenames are entrypoint-specific:

```text
storage/cache/framework/config/application-http.cache
storage/cache/framework/config/application-cli.cache
```

HTTP and CLI therefore use separate cache artifacts.

A cryptographically random integrity key is created automatically at:

```text
storage/writable/config-cache/application-config-cache.key
```

The key is stored separately from cache payloads. No additional environment variable is required.
Its initialization uses an exclusive OS lock so concurrent first requests converge on one complete
key.

### What the cache contains

The generated file contains a versioned JSON envelope and HMAC-SHA-256 over a typed data payload with:

- cache format version
- target entrypoint (`http` or `cli`)
- tracked source files
  - relative path
  - whether the file existed when the cache was built
- SHA-256 hash for existing files
- tracked environment values actually used during config resolution
- scalar and array data for typed `ConfigDefinitionInterface` payloads

Manifest validation changes invalidate older cache format versions. After a framework upgrade, the
first production HTTP request or CLI command may therefore rebuild its entrypoint cache once.

The cache does not store live services, container state, providers, routes, or middleware.
String, integer, float, boolean, null, nested array, and integer/string array-key identity are encoded
explicitly. PHP objects are not serialized, arbitrary classes are not reconstructed from raw cache
values, and no unsigned fallback exists.

The compiled payload can contain `APP_SECRET`, database passwords, and other resolved credentials.
Managed cache and key directories therefore use mode `0700`; payload and key files use `0600`.
Storage inside or overlapping the public web root, symbolic-link components, traversal, and unsafe
file types are rejected with an explicit exception.

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
   -> on cache hit: verify HMAC and hydrate typed definitions from validated data
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
- cache HMAC and typed value structure
- target entrypoint
- source file existence state
- source file SHA-256 hashes
- environment values that were actually read during config resolution

This means:

- changing a tracked YAML or explicit PHP config-definition source invalidates the cache
- changing a tracked `ConfigMap.php` file invalidates the cache
- changing a used environment value invalidates the cache
- adding a config file that is already listed in `Config.yaml` invalidates the cache because a previously missing tracked candidate changes from `exists: false` to `exists: true`
- removing a previously existing tracked config file invalidates the cache for the same reason
- changing `Config.yaml` invalidates the cache because the manifest itself is tracked
- adding `Config.yaml` or `Config.yml` to an application that previously had no manifest invalidates
  the zero-config cache entry and loads the newly declared sources

The cache does not rebuild when:

- source files are unchanged
- used environment values are unchanged
- unrelated environment variables change
- files not referenced by the current config manifest / loader path change

### Source tracking details

`ConfigLoader` tracks:

- both `app/Config/Config.yaml` and `Config.yml` candidates, including their missing state
- every candidate config source path resolved from the manifest
- every actual loaded YAML file or explicit PHP config-definition source
- each YAML directory `ConfigMap.php` used during loading

For environment tracking, the loader stores only environment keys that were actually consumed by YAML `$env` resolution. Unused environment variables are not part of cache freshness.

### Corrupted or invalid cache artifacts

If the generated cache file:

- does not exist
- contains malformed JSON or a payload with the wrong version
- has an invalid HMAC or unsupported typed value structure
- has the wrong entrypoint
- has invalid payload structure
- references source metadata that no longer matches
- references tracked environment values that no longer match

then `ApplicationConfigCache::loadIfFresh()` returns `null` and runtime falls back to the normal source-loading path. A fresh cache is then written in production.

### Write behavior and concurrency

The writer creates a random, exclusively opened temporary file in the final cache directory, applies
mode `0600`, writes and synchronizes the complete authenticated envelope, and atomically renames it
over the previous artifact. It never unlinks the live cache before publication. Concurrent readers
therefore observe either the complete previous file or the complete replacement. Concurrent cache
misses may still duplicate source parsing, but their publications remain complete and independently
validated.

Corrupted, modified, oversized, unknown-version, or stale data is treated as a cache miss. Unsafe
paths, permissions, symbolic links, key files, or storage boundaries fail explicitly instead of
silently disabling protection.

### Source of truth and safe manual operations

YAML files, explicit PHP config-definition sources, and environment values are authoritative. Generated cache files:

- are not source of truth
- should not be edited manually
- do not need to be committed
- are safe to delete

Manual clear today means removing only the framework-owned generated files:

```text
storage/cache/framework/config/application-http.cache
storage/cache/framework/config/application-cli.cache
```

After manual clear, the next production HTTP request or CLI command rebuilds the corresponding artifact automatically.
Do not delete the integrity key while processes are writing cache files. Deliberate key rotation safely
invalidates existing `.cache` files; delete those artifacts and let the next bootstrap rebuild them.

Legacy `application-http.php` and `application-cli.php` artifacts are never read or executed. Remove
them after stopping processes that run an older framework version. They are not migrated or used as
a fallback.

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
-> tracked SHA-256 no longer matches
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
