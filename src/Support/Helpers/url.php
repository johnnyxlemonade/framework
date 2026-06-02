<?php

declare(strict_types=1);

use Lemonade\Framework\Http\Request\ServerHelper;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Support\BaseUrlResolver;
use Psr\Http\Message\ServerRequestInterface;

if (!function_exists('url')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->url().
     *
     * @param array<string, scalar|null> $params
     */
    function url(string $route, array $params = []): string
    {
        $generator = service(UrlGenerator::class);

        if (!$generator instanceof UrlGenerator) {
            return '';
        }

        return $generator->route($route, $params);
    }
}

if (!function_exists('localized_url')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->localizedUrl().
     *
     * @param array<string, scalar|null> $params
     */
    function localized_url(string $route, array $params = [], ?string $locale = null): string
    {
        $generator = service(UrlGenerator::class);

        if (!$generator instanceof UrlGenerator) {
            return '';
        }

        return $generator->localizedRoute($route, $params, $locale);
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $request = service(ServerRequestInterface::class);
        if ($request instanceof ServerRequestInterface) {
            return $request->getUri()->getPath();
        }

        $uri = ServerHelper::get('REQUEST_URI', '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }
}

if (!function_exists('current_query')) {
    function current_query(): string
    {
        $request = service(ServerRequestInterface::class);
        if ($request instanceof ServerRequestInterface) {
            return $request->getUri()->getQuery();
        }

        $uri = ServerHelper::get('REQUEST_URI', '');
        $query = parse_url($uri, PHP_URL_QUERY);

        return is_string($query) ? $query : '';
    }
}

if (!function_exists('current_url')) {
    function current_url(bool $withQuery = true): string
    {
        $request = service(ServerRequestInterface::class);
        if ($request instanceof ServerRequestInterface) {
            $uri = $request->getUri();
            if ($withQuery) {
                return (string) $uri;
            }

            $path = $uri->getPath();

            return $path !== '' ? $path : '/';
        }

        $path = current_path();
        if (!$withQuery) {
            return $path;
        }

        $query = current_query();
        if ($query === '') {
            return $path;
        }

        return $path . '?' . $query;
    }
}

if (!function_exists('current_full_url')) {
    function current_full_url(bool $withQuery = true): string
    {
        $request = service(ServerRequestInterface::class);
        if ($request instanceof ServerRequestInterface) {
            $uri = $request->getUri();

            if ($withQuery) {
                return (string) $uri;
            }

            return $uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath();
        }

        $baseUrl = service(BaseUrlResolver::class);
        if (!$baseUrl instanceof BaseUrlResolver) {
            return current_url($withQuery);
        }

        $url = $baseUrl->baseUrl(current_path());
        if (!$withQuery) {
            return $url;
        }

        $query = current_query();
        if ($query === '') {
            return $url;
        }

        return $url . '?' . $query;
    }
}

if (!function_exists('is_url_active')) {
    function is_url_active(string $url, bool $startsWith = false): bool
    {
        $current = current_url(false);

        if ($startsWith) {
            return str_starts_with(rtrim($current, '/'), rtrim($url, '/'));
        }

        return rtrim($current, '/') === rtrim($url, '/');
    }
}

if (!function_exists('is_route_active')) {
    /**
     * @param array<string, scalar|null> $params
     */
    function is_route_active(string $route, array $params = [], bool $startsWith = false): bool
    {
        $target = url($route, $params);

        if ($target === '') {
            return false;
        }

        return is_url_active($target, $startsWith);
    }
}
