<?php

declare(strict_types=1);

if (!function_exists('url')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->url().
     *
     * @param array<string, scalar|null> $params
     */
    function url(string $route, array $params = []): string
    {
        throw new LogicException('The global url() helper no longer resolves framework services. In views use $helpers->url(); in controllers use $this->url(); elsewhere inject UrlGenerator explicitly.');
    }
}

if (!function_exists('localized_url')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->localizedUrl().
     *
     * @param array<string, scalar|null> $params
     */
    function localized_url(string $route, array $params = [], ?string $locale = null): string
    {
        throw new LogicException('The global localized_url() helper no longer resolves framework services. In views use $helpers->localizedUrl(); elsewhere inject UrlGenerator explicitly.');
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        throw new LogicException('The global current_path() helper no longer resolves request state. In views use $requestHelpers->currentPath(); elsewhere pass ServerRequestInterface explicitly.');
    }
}

if (!function_exists('current_query')) {
    function current_query(): string
    {
        throw new LogicException('The global current_query() helper no longer resolves request state. In views use $requestHelpers->currentQuery(); elsewhere pass ServerRequestInterface explicitly.');
    }
}

if (!function_exists('current_url')) {
    function current_url(bool $withQuery = true): string
    {
        throw new LogicException('The global current_url() helper no longer resolves request state. In views use $requestHelpers->currentUrl(); elsewhere pass ServerRequestInterface explicitly.');
    }
}

if (!function_exists('current_full_url')) {
    function current_full_url(bool $withQuery = true): string
    {
        throw new LogicException('The global current_full_url() helper no longer resolves request state. In views use $requestHelpers->currentFullUrl(); elsewhere pass ServerRequestInterface explicitly.');
    }
}

if (!function_exists('is_url_active')) {
    function is_url_active(string $url, bool $startsWith = false): bool
    {
        throw new LogicException('The global is_url_active() helper no longer resolves request state. In views use $requestHelpers->isUrlActive(); elsewhere pass ServerRequestInterface explicitly.');
    }
}

if (!function_exists('is_route_active')) {
    /**
     * @param array<string, scalar|null> $params
     */
    function is_route_active(string $route, array $params = [], bool $startsWith = false): bool
    {
        throw new LogicException('The global is_route_active() helper no longer resolves framework services. In views use $requestHelpers->isRouteActive(); elsewhere inject UrlGenerator and pass ServerRequestInterface explicitly.');
    }
}
