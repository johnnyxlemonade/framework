# Configuration

Configuration files are plain PHP files returning arrays.

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

## Provider and command keys

Framework-level providers are read from the framework default configuration key:

```text
framework.providers
```

Application-level providers are read from:

```text
providers
```

CLI commands are read from:

```text
commands
```

## Environment helper

Environment values can be read through `Lemonade\Framework\Support\Env`. The helper resolves values from `$_ENV`, then `$_SERVER`, then `getenv()`.

Example configuration file:

```php
<?php

use Lemonade\Framework\Support\Env;

return [
    'app' => [
        'name' => Env::string('APP_NAME', 'My Application'),
    ],

    'localization' => [
        'default_locale' => Env::string('APP_LOCALE', 'cs'),
        'fallback_locale' => Env::string('APP_FALLBACK_LOCALE', 'cs'),
        'supported_locales' => Env::list('APP_SUPPORTED_LOCALES', ['cs', 'en']),
    ],
];
```

`Env::list()` expects a comma-separated string and returns a normalized list of unique, non-empty values.

```bash
APP_SUPPORTED_LOCALES=cs,en,de
```

Result:

```php
['cs', 'en', 'de']
```
