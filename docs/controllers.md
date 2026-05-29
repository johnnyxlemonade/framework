# Controllers

Controllers extend `Lemonade\Framework\Core\Controller`.

A controller action may return a PSR response directly. Scalar, stringable and `null` return values are normalized into HTML responses.

## Basic controller

```php
<?php

namespace App\Controllers;

use Lemonade\Framework\Core\Controller;
use Psr\Http\Message\ResponseInterface;

final class HomeController extends Controller
{
    public function index(): ResponseInterface
    {
        return $this->html('<h1>Hello</h1>');
    }
}
```

## Scalar return values

```php
public function index(): string
{
    return 'Hello';
}
```

## Route parameters

Route parameters are injected by parameter name and cast to scalar types when possible.

```php
public function detail(int $id): ResponseInterface
{
    return $this->json([
        'id' => $id,
    ]);
}
```

## Current request injection

The current PSR server request can be injected into an action:

```php
use Psr\Http\Message\ServerRequestInterface;

public function store(ServerRequestInterface $request): ResponseInterface
{
    // ...
}
```

## Request and response helpers

The base controller provides helpers for common request and response tasks:

```php
$this->query('page', 1);
$this->post('name');
$this->jsonPayload();
$this->file('image');

$this->text('OK');
$this->html('<h1>OK</h1>');
$this->json(['ok' => true]);
$this->redirect('/login');
$this->download($path);
$this->stream($producer);
```

## Framework service helpers

Controllers can access commonly used framework services:

```php
$this->url();
$this->validator();
$this->translator();
$this->filesystem();
$this->view();
$this->flash();
$this->breadcrumb();
```

These helpers are convenience methods around configured framework services. They do not replace constructor injection for application services.
