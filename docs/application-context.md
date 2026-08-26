# Application Context

The application context describes the runtime environment and the application paths used by both HTTP and CLI execution.

It is usually created through `ApplicationContextFactory::fromGlobals($basePath)`. The factory normalizes the base path, loads a `.env` file from that base path and then resolves context values from `$_ENV`, `$_SERVER` and `getenv()`.

## Environment resolution

Environment values are resolved in this order:

```text
$_ENV
-> $_SERVER
-> getenv()
-> default value
```

## Important environment variables

```bash
APP_ENV=development
APP_DEBUG=true
APP_BASE_PATH=/path/to/application
APP_PUBLIC_PATH=/path/to/public-root
```

`APP_ENV` defines the current runtime environment. When it is not provided, the framework defaults to `production`.

`APP_DEBUG` explicitly enables or disables debug mode. When it is not provided, the default is derived from the selected environment.

`APP_BASE_PATH` can override the base path passed to the context factory. This is useful when the entrypoint path and application root path are not the same.

`APP_PUBLIC_PATH` can explicitly override the public web root used for assets and uploads. Relative values are resolved against the application base path.

## Resolved paths

The context exposes:

- current environment
- debug mode
- base path
- public path
- application path
- configuration path
- storage path
- writable path
- log path
- session path
- upload path
- cache path

By convention, paths are resolved from the base path as follows:

```text
base path      -> /
public path    -> APP_PUBLIC_PATH
               -> dirname(SCRIPT_FILENAME) when HTTP entrypoint is index.php inside base path
               -> public/ when that directory exists
               -> / (backward-compatible fallback)
application    -> app/
configuration  -> app/Config/
storage        -> storage/
logs           -> storage/writable/logs/
sessions       -> storage/writable/sessions/
uploads        -> <public path>/uploads/
cache          -> storage/cache/
```

Absolute paths are preserved and normalized. Relative paths are resolved against the appropriate base, public, application or storage directory.

## Usage

Config files and services should use the application context instead of hardcoding filesystem paths.
