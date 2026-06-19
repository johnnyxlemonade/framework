# Configuration

Configuration files are plain PHP files returning typed config definition objects.

The framework resolves application configuration from the application context. By convention, configuration files are stored in:

```text
app/Config/
```

## HTTP configuration files

The HTTP kernel conventionally loads these configuration files:

```text
app/Config/App.php
app/Config/Localization.php
app/Config/Cache.php
app/Config/Logging.php
app/Config/Session.php
app/Config/Database.php
app/Config/Breadcrumbs.php
app/Config/Upload.php
app/Config/Providers.php
```

The HTTP kernel loads routes separately after providers have been registered:

```text
app/Config/Routing.php
```

## CLI configuration files

The CLI kernel conventionally loads the same application configuration files as the HTTP kernel, except routing, and additionally loads:

```text
app/Config/Commands.php
```

## Config manifest

The application config manifest returns only file lists:

```php
<?php

declare(strict_types=1);

return [
    'shared' => [
        'App.php',
        'Api.php',
        'Providers.php',
    ],
    'http' => [
        'HtmlMinify.php',
    ],
    'cli' => [
        'Commands.php',
    ],
];
```

Every listed config file must return an implementation of `ConfigDefinitionInterface`. Raw array config files are rejected.

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

Example typed configuration files:

```php
<?php

declare(strict_types=1);

use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Support\Env;

return AppConfigDefinition::create()
    ->baseUrl(Env::string('APP_URL'));
```

```php
<?php

declare(strict_types=1);

use App\Api\AppApiEndpointProvider;
use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Support\Env;

return ApiConfigDefinition::create()
    ->enabled()
    ->prefix('/api')
    ->endpointProvider(AppApiEndpointProvider::class)
    ->staticBearer(
        token: Env::string('API_TOKEN'),
        scopes: ['api:admin', 'openapi:read'],
    )
    ->docsEnabled();
```

`Env::list()` expects a comma-separated string and returns a normalized list of unique, non-empty values.

```bash
APP_SUPPORTED_LOCALES=cs,en,de
```

Result:

```php
['cs', 'en', 'de']
```
