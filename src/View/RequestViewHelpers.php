<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RequestViewHelpers
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly UrlGenerator $urlGenerator,
        private readonly ?FlashBagInterface $flash = null,
        private readonly ?SessionInterface $session = null,
    ) {}

    public function old(string $key, mixed $default = ''): mixed
    {
        if ($this->session instanceof SessionInterface) {
            $value = $this->session->get('_old_input.' . $key, null);
            if ($value !== null) {
                return $value;
            }
        }

        if (!$this->flash instanceof FlashBagInterface) {
            return $default;
        }

        $values = $this->flash->get('old_input', []);
        if (!is_array($values)) {
            return $default;
        }

        return $values[$key] ?? $default;
    }

    public function flash(string $key, mixed $default = null): mixed
    {
        if (!$this->flash instanceof FlashBagInterface) {
            return $default;
        }

        return $this->flash->pull($key, $default);
    }

    public function currentPath(): string
    {
        return $this->request->getUri()->getPath();
    }

    public function currentQuery(): string
    {
        return $this->request->getUri()->getQuery();
    }

    public function currentUrl(bool $withQuery = true): string
    {
        $uri = $this->request->getUri();
        if ($withQuery) {
            return (string) $uri;
        }

        $path = $uri->getPath();

        return $path !== '' ? $path : '/';
    }

    public function currentFullUrl(bool $withQuery = true): string
    {
        $uri = $this->request->getUri();

        if ($withQuery) {
            return (string) $uri;
        }

        return $uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath();
    }

    public function isUrlActive(string $url, bool $startsWith = false): bool
    {
        $current = $this->currentUrl(false);

        if ($startsWith) {
            return str_starts_with(rtrim($current, '/'), rtrim($url, '/'));
        }

        return rtrim($current, '/') === rtrim($url, '/');
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function isRouteActive(string $route, array $params = [], bool $startsWith = false): bool
    {
        return $this->isUrlActive(
            $this->urlGenerator->route($route, $params),
            $startsWith,
        );
    }
}
