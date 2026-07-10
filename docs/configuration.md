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
    $env: APP_URL
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
