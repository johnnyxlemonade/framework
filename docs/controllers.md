# Controllers

Controllers extend `Lemonade\Framework\Core\AbstractController`.

A controller action may return a PSR response directly. Scalar, stringable and `null` return values are normalized into HTML responses.

## Basic controller

```php
<?php

namespace App\Controllers;

use Lemonade\Framework\Core\AbstractController;
use Psr\Http\Message\ResponseInterface;

final class HomeController extends AbstractController
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

`controllerService()` is intentionally narrower than constructor DI. Use it in application base controllers for infrastructure or request context services only. Concrete action controllers should receive their business dependencies through the constructor.

```php
use Lemonade\Framework\Core\AbstractController;
use Psr\Http\Message\ResponseInterface;

abstract class AppController extends AbstractController
{
    /**
     * @param array<string, mixed> $data
     */
    protected function page(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->html(
            $this->view()->template('layouts.app', $view, $data),
            $status,
        );
    }
}

final class DocumentationController extends AppController
{
    public function __construct(
        private readonly DocumentationCatalogInterface $documentation,
    ) {}
}
```
