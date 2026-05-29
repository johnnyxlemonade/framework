# Routing

Routes are registered through the router, usually in `app/Config/Routing.php`.

The router maps HTTP methods and paths to controller actions using the `Controller@action` format.

## Basic routes

```php
<?php

use Lemonade\Framework\Routing\Router;

return static function (Router $router): void {
    $router->getNamed('home', '/', 'HomeController@index');

    $router
        ->get('/articles/{id}', 'ArticleController@detail')
        ->name('article.detail');
};
```

Controller names are resolved against the application controller namespace unless a fully qualified class name is used.

## Named routes and URL generation

Named routes can be used for URL generation:

```php
$url = service('url')->route('article.detail', ['id' => 123]);
```

Extra parameters become query parameters.

```php
$url = service('url')->route('article.detail', [
    'id' => 123,
    'preview' => 1,
]);
```

Result:

```text
/articles/123?preview=1
```

## Route groups

```php
$router->group('/admin', static function (Router $router): void {
    $router->getNamed('dashboard', '/dashboard', 'Admin\DashboardController@index');
});
```

## Localized route groups

```php
$router->localizedGroup(static function (Router $router): void {
    $router->getNamed('home', '/', 'HomeController@index');
});
```

Localized routes are useful when the application needs language-aware URLs while still keeping route definitions centralized.
