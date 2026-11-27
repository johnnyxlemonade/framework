<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Http\Request\HttpMethod;
use Psr\Http\Message\ServerRequestInterface;

final class RouteCollection
{
    /**
     * @var array<string, array<string, Route>>
     */
    private array $routes = [];

    /**
     * @var list<string>
     */
    private const array METHOD_ORDER = [
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
        if (isset($this->routes[$route->method()][$route->path()])) {
            throw new \LogicException(sprintf(
                'Route "%s %s" is already registered.',
                $route->method(),
                $route->path(),
            ));
        }

        $this->routes[$route->method()][$route->path()] = $route;
    }

    public function match(string $method, string $path, ?ServerRequestInterface $request = null): ?RouteMatch
    {
        $methodName = $this->normalizeMethod($method);
        $normalizedPath = RoutePathNormalizer::normalize($path);

        if (!isset($this->routes[$methodName])) {
            return null;
        }

        if (isset($this->routes[$methodName][$normalizedPath])) {
            return $this->toMatch($this->routes[$methodName][$normalizedPath]);
        }

        foreach ($this->routes[$methodName] as $route) {
            $params = $this->matchRoutePath($route, $normalizedPath, $request);
            if ($params !== null) {
                return $this->toMatch($route, $params);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function allowedMethodsForPath(string $path, ?ServerRequestInterface $request = null): array
    {
        $normalizedPath = RoutePathNormalizer::normalize($path);
        $allowed = [];

        foreach ($this->routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                if ($this->pathMatchesRoute($route, $normalizedPath, $request)) {
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

    public function hasExplicitRouteForPath(HttpMethod|string $method, string $path, ?ServerRequestInterface $request = null): bool
    {
        $methodName = $this->normalizeMethod($method);
        $normalizedPath = RoutePathNormalizer::normalize($path);

        if (!isset($this->routes[$methodName])) {
            return false;
        }

        foreach ($this->routes[$methodName] as $route) {
            if ($this->pathMatchesRoute($route, $normalizedPath, $request)) {
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

    /**
     * @param array<string, string> $params
     */
    private function toMatch(Route $route, array $params = []): RouteMatch
    {
        return new RouteMatch(
            controllerAction: $route->controllerAction(),
            params: $params,
            middleware: $route->middlewareStack(),
            name: $route->routeName(),
            method: $route->method(),
            path: $route->path(),
        );
    }

    private function pathMatchesRoute(Route $route, string $path, ?ServerRequestInterface $request): bool
    {
        return $this->matchRoutePath($route, $path, $request) !== null;
    }

    /**
     * @return array<string, string>|null
     */
    private function matchRoutePath(Route $route, string $actualPath, ?ServerRequestInterface $request): ?array
    {
        $routePath = $route->path();

        if ($routePath === $actualPath) {
            return [];
        }

        $routeSegments = $this->splitRouteSegments($routePath);
        $pathSegments = $this->splitDecodedPathSegments($actualPath);

        if (!str_contains($routePath, '{')) {
            return $routeSegments === $pathSegments ? [] : null;
        }

        $params = $this->extractPathParams($routeSegments, $pathSegments);
        if ($params === null) {
            return null;
        }

        foreach ($route->parameterConstraints() as $name => $allowedValues) {
            if (isset($params[$name]) && !in_array($params[$name], $allowedValues, true)) {
                return null;
            }
        }
        $predicates = $route->parameterConstraintPredicates();
        foreach ($params as $name => $value) {
            if (isset($predicates[$name]) && !$predicates[$name]($value, $request)) {
                return null;
            }
        }

        return $params;
    }

    /**
     * @param list<string> $routeSegments
     * @param list<string> $pathSegments
     * @return array<string, string>|null
     */
    private function extractPathParams(array $routeSegments, array $pathSegments): ?array
    {
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
                $name = $matches[1];
                $value = $pathSegments[$pathIndex];

                $params[$name] = $value;
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

    /**
     * @return list<string>
     */
    private function splitRouteSegments(string $path): array
    {
        return $this->splitPathSegments($path);
    }

    /**
     * @return list<string>
     */
    private function splitDecodedPathSegments(string $path): array
    {
        return array_map(
            static fn(string $segment): string => rawurldecode($segment),
            $this->splitPathSegments($path),
        );
    }

    /**
     * @return list<string>
     */
    private function splitPathSegments(string $path): array
    {
        return array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));
    }

}
