<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Http\Request\HttpMethod;

final class RouteCollection
{
    /**
     * @var array<string, array<string, Route>>
     */
    private array $routes = [];

    /**
     * @var list<string>
     */
    private const METHOD_ORDER = [
        'GET',
        'HEAD',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ];

    public function add(Route $route): void
    {
        $this->routes[$route->method()][$route->path()] = $route;
    }

    public function match(string $method, string $path): ?RouteMatch
    {
        $methodName = $this->normalizeMethod($method);
        $normalizedPath = $this->normalizePath($path);

        if (!isset($this->routes[$methodName])) {
            return null;
        }

        if (isset($this->routes[$methodName][$normalizedPath])) {
            return $this->toMatch($this->routes[$methodName][$normalizedPath]);
        }

        foreach ($this->routes[$methodName] as $routePath => $route) {
            $params = $this->extractPathParams($routePath, $normalizedPath);
            if ($params !== null) {
                return $this->toMatch($route, $params);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function allowedMethodsForPath(string $path): array
    {
        $normalizedPath = $this->normalizePath($path);
        $allowed = [];

        foreach ($this->routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $routePath => $_) {
                if ($this->pathMatchesRoute($routePath, $normalizedPath)) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        if (in_array('GET', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        if ($allowed !== []) {
            $allowed[] = 'OPTIONS';
        }

        return self::sortMethods($allowed);
    }

    public function hasExplicitRouteForPath(HttpMethod|string $method, string $path): bool
    {
        $methodName = $this->normalizeMethod($method);
        $normalizedPath = $this->normalizePath($path);

        if (!isset($this->routes[$methodName])) {
            return false;
        }

        foreach ($this->routes[$methodName] as $routePath => $_) {
            if ($this->pathMatchesRoute($routePath, $normalizedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $methods
     * @return list<string>
     */
    public static function sortMethods(array $methods): array
    {
        $methods = array_values(array_unique($methods));

        usort($methods, static function (string $a, string $b): int {
            $aPos = array_search($a, self::METHOD_ORDER, true);
            $bPos = array_search($b, self::METHOD_ORDER, true);
            $aIndex = $aPos === false ? PHP_INT_MAX : $aPos;
            $bIndex = $bPos === false ? PHP_INT_MAX : $bPos;

            if ($aIndex === $bIndex) {
                return strcmp($a, $b);
            }

            return $aIndex <=> $bIndex;
        });

        return $methods;
    }

    private function normalizeMethod(HttpMethod|string $method): string
    {
        return $method instanceof HttpMethod
            ? $method->value
            : strtoupper($method);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * @param array<string, string> $params
     */
    private function toMatch(Route $route, array $params = []): RouteMatch
    {
        return new RouteMatch(
            controller: $route->controller(),
            action: $route->action(),
            params: $params,
            middleware: $route->middlewareStack(),
        );
    }

    private function pathMatchesRoute(string $routePath, string $path): bool
    {
        if ($routePath === $path) {
            return true;
        }

        return $this->extractPathParams($routePath, $path) !== null;
    }

    /**
     * @return array<string, string>|null
     */
    private function extractPathParams(string $routePath, string $actualPath): ?array
    {
        if (!str_contains($routePath, '{')) {
            return null;
        }

        $routeSegments = array_values(array_filter(
            explode('/', trim($routePath, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));
        $pathSegments = array_values(array_filter(
            explode('/', trim($actualPath, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));

        $params = [];
        $pathIndex = 0;
        $routeCount = count($routeSegments);

        foreach ($routeSegments as $routeIndex => $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*):any}$/', $segment, $matches) === 1) {
                $name = $matches[1];

                $remainingRouteSegments = $routeCount - $routeIndex - 1;
                $remainingPathSegments = array_slice(
                    $pathSegments,
                    $pathIndex,
                    count($pathSegments) - $pathIndex - $remainingRouteSegments,
                );

                if ($remainingPathSegments === []) {
                    return null;
                }

                $params[$name] = implode('/', $remainingPathSegments);
                $pathIndex += count($remainingPathSegments);

                continue;
            }

            if (!isset($pathSegments[$pathIndex])) {
                return null;
            }

            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)}$/', $segment, $matches) === 1) {
                $params[$matches[1]] = $pathSegments[$pathIndex];
                $pathIndex++;

                continue;
            }

            if ($segment !== $pathSegments[$pathIndex]) {
                return null;
            }

            $pathIndex++;
        }

        if ($pathIndex !== count($pathSegments)) {
            return null;
        }

        return $params;
    }
}
