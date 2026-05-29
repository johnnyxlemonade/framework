<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Routing\Exception\MissingRouteParameterException;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

final class Router
{
    /**
     * @var array<int, Route>
     */
    private array $routeList = [];

    /**
     * @var array<string, string>
     */
    private array $namedRoutes = [];

    /**
     * @var array<int, string>
     */
    private array $groupPrefixes = [];
    /**
     * @var array<int, string>
     */
    private array $namePrefixes = [];

    private string $controllerNamespace = 'App\\Controllers';
    private string $localizedRouteNamePrefix = 'localized.';
    private string $localizedRoutePrefix = '/{locale}';
    private string $localizedLocaleParameter = 'locale';

    private readonly RouteCollection $collection;

    public function __construct()
    {
        $this->collection = new RouteCollection();
    }

    public function get(string $path, string $handler): Route
    {
        return $this->map('GET', $path, $handler);
    }

    public function post(string $path, string $handler): Route
    {
        return $this->map('POST', $path, $handler);
    }

    public function put(string $path, string $handler): Route
    {
        return $this->map('PUT', $path, $handler);
    }

    public function patch(string $path, string $handler): Route
    {
        return $this->map('PATCH', $path, $handler);
    }

    public function delete(string $path, string $handler): Route
    {
        return $this->map('DELETE', $path, $handler);
    }

    public function head(string $path, string $handler): Route
    {
        return $this->map('HEAD', $path, $handler);
    }

    public function options(string $path, string $handler): Route
    {
        return $this->map('OPTIONS', $path, $handler);
    }

    public function getNamed(string $name, string $path, string $handler): Route
    {
        return $this->mapNamed($name, 'GET', $path, $handler);
    }

    public function postNamed(string $name, string $path, string $handler): Route
    {
        return $this->mapNamed($name, 'POST', $path, $handler);
    }

    public function putNamed(string $name, string $path, string $handler): Route
    {
        return $this->mapNamed($name, 'PUT', $path, $handler);
    }

    public function patchNamed(string $name, string $path, string $handler): Route
    {
        return $this->mapNamed($name, 'PATCH', $path, $handler);
    }

    public function deleteNamed(string $name, string $path, string $handler): Route
    {
        return $this->mapNamed($name, 'DELETE', $path, $handler);
    }

    public function headNamed(string $name, string $path, string $handler): Route
    {
        return $this->mapNamed($name, 'HEAD', $path, $handler);
    }

    public function optionsNamed(string $name, string $path, string $handler): Route
    {
        return $this->mapNamed($name, 'OPTIONS', $path, $handler);
    }

    public function map(HttpMethod|string $method, string $path, string $handler): Route
    {
        [$controller, $action] = $this->parseHandler($handler);

        $methodName = $this->normalizeMethod($method);
        $normalizedPath = $this->withGroupPrefix($path);

        $route = new Route(
            method: $methodName,
            path: $normalizedPath,
            controller: $controller,
            action: $action,
        );

        $this->collection->add($route);
        $this->routeList[] = $route;

        return $route;
    }

    public function mapNamed(string $name, HttpMethod|string $method, string $path, string $handler): Route
    {
        $resolvedName = $this->withNamePrefix($name);

        if (isset($this->namedRoutes[$resolvedName])) {
            throw new \LogicException(sprintf(
                'Named route "%s" is already registered as "%s".',
                $resolvedName,
                $this->namedRoutes[$resolvedName],
            ));
        }

        $route = $this->map($method, $path, $handler);

        $route->name($resolvedName);

        $this->namedRoutes[$resolvedName] = $this->formatUrl($route->path());

        return $route;
    }

    public function group(string $prefix, callable $builder): RouteGroup
    {
        $before = count($this->routeList);

        $this->groupPrefixes[] = $this->normalizePath($prefix);

        try {
            $builder($this);
        } finally {
            array_pop($this->groupPrefixes);
        }

        return new RouteGroup(
            array_slice($this->routeList, $before),
        );
    }

    public function localizedGroup(callable $builder): RouteGroup
    {
        $before = count($this->routeList);

        $builder($this);

        $this->group($this->localizedRoutePrefix, function (Router $router) use ($builder): void {
            $this->namePrefixes[] = $this->localizedRouteNamePrefix;

            try {
                $builder($router);
            } finally {
                array_pop($this->namePrefixes);
            }
        });

        return new RouteGroup(
            array_slice($this->routeList, $before),
        );
    }

    public function configureLocalizedRoutes(
        string $routeNamePrefix = 'localized.',
        ?string $routePrefix = null,
        string $localeParameter = 'locale',
    ): void {
        $localeParameter = trim($localeParameter);
        $this->localizedLocaleParameter = $localeParameter !== '' ? $localeParameter : 'locale';
        $this->localizedRouteNamePrefix = $routeNamePrefix;

        if ($routePrefix !== null && trim($routePrefix) !== '') {
            $placeholder = '{' . $this->localizedLocaleParameter . '}';
            if (!str_contains($routePrefix, $placeholder)) {
                throw new \InvalidArgumentException(sprintf(
                    'Localized route prefix "%s" must contain placeholder "%s".',
                    $routePrefix,
                    $placeholder,
                ));
            }

            $this->localizedRoutePrefix = $routePrefix;

            return;
        }

        $this->localizedRoutePrefix = '/{' . $this->localizedLocaleParameter . '}';
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw RouteNotFoundException::forName($name);
        }

        return $this->buildUrl($this->namedRoutes[$name], $params);
    }

    public function setControllerNamespace(string $namespace): void
    {
        $this->controllerNamespace = trim($namespace, '\\');
    }

    public function match(ServerRequestInterface $request): RouteMatch
    {
        $method = strtoupper($request->getMethod());
        $path = $this->normalizePath($request->getUri()->getPath());

        $candidateMethods = $method === 'HEAD' ? ['HEAD', 'GET'] : [$method];
        foreach ($candidateMethods as $candidateMethod) {
            $match = $this->collection->match($candidateMethod, $path);
            if ($match !== null) {
                return $match;
            }
        }

        if ($method === 'GET' || $method === 'HEAD') {
            $resolved = $this->resolveConventionRoute($path);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        throw RouteNotFoundException::forRequest(
            $method,
            (string) $request->getUri(),
        );
    }

    /**
     * @return list<string>
     */
    public function allowedMethodsForPath(string $path): array
    {
        $normalizedPath = $this->normalizePath($path);
        $allowed = $this->collection->allowedMethodsForPath($normalizedPath);

        if ($allowed === [] && $this->resolveConventionRoute($normalizedPath) !== null) {
            return RouteCollection::sortMethods([
                'GET',
                'HEAD',
                'OPTIONS',
            ]);
        }

        return RouteCollection::sortMethods($allowed);
    }

    public function hasExplicitRouteForPath(HttpMethod|string $method, string $path): bool
    {
        return $this->collection->hasExplicitRouteForPath($method, $path);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function buildUrl(string $path, array $params): string
    {
        $used = [];

        $url = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::any)?\}/',
            static function (array $matches) use ($params, &$used): string {
                $key = $matches[1];

                if (!array_key_exists($key, $params)) {
                    throw new MissingRouteParameterException($key);
                }

                $value = $params[$key];

                if ($value === null) {
                    throw new \InvalidArgumentException(sprintf(
                        'Route parameter "%s" cannot be null.',
                        $key,
                    ));
                }

                $used[] = $key;

                return implode(
                    '/',
                    array_map(
                        static fn(string $segment): string => rawurlencode($segment),
                        explode('/', trim((string) $value, '/')),
                    ),
                );
            },
            $path,
        );

        if ($url === null) {
            throw new \RuntimeException(sprintf(
                'Failed to generate URL for path "%s".',
                $path,
            ));
        }

        $query = array_diff_key($params, array_flip($used));

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function resolveConventionRoute(string $path): ?RouteMatch
    {
        if ($path === '/') {
            $controller = $this->buildControllerClass(['home']);

            return class_exists($controller)
                ? new RouteMatch($controller, 'index')
                : null;
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return null;
        }

        $controllerA = $this->buildControllerClass($segments);

        if (class_exists($controllerA)) {
            return new RouteMatch($controllerA, 'index');
        }

        if (count($segments) >= 2) {
            $action = array_pop($segments);
            $controllerB = $this->buildControllerClass($segments);

            if (class_exists($controllerB)) {
                return new RouteMatch($controllerB, $action);
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $segments
     */
    private function buildControllerClass(array $segments): string
    {
        $segments = array_map(
            static fn(string $segment): string => str_replace(
                ' ',
                '',
                ucwords(str_replace(['-', '_'], ' ', $segment)),
            ),
            $segments,
        );

        $last = array_pop($segments);

        if ($last === null) {
            return $this->controllerNamespace . '\\HomeController';
        }

        $controller = $last . 'Controller';
        $prefix = $segments === [] ? '' : implode('\\', $segments) . '\\';

        return $this->controllerNamespace . '\\' . $prefix . $controller;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseHandler(string $handler): array
    {
        if (!str_contains($handler, '@')) {
            throw new \InvalidArgumentException(sprintf(
                'Route handler "%s" must use "Controller@action" format.',
                $handler,
            ));
        }

        [$controller, $action] = explode('@', $handler, 2);

        $controller = trim($controller);
        $action = trim($action);

        if ($controller === '' || $action === '') {
            throw new \InvalidArgumentException(sprintf(
                'Route handler "%s" must contain controller and action.',
                $handler,
            ));
        }

        return [
            $this->resolveControllerClass($controller),
            $action,
        ];
    }

    private function resolveControllerClass(string $controller): string
    {
        $controller = trim($controller, '\\');

        if (str_starts_with($controller, $this->controllerNamespace . '\\')) {
            return $controller;
        }

        if (class_exists($controller)) {
            return $controller;
        }

        return $this->controllerNamespace . '\\' . $controller;
    }

    private function normalizeMethod(HttpMethod|string $method): string
    {
        return $method instanceof HttpMethod
            ? $method->value
            : strtoupper($method);
    }

    private function withGroupPrefix(string $path): string
    {
        $prefix = implode('', $this->groupPrefixes);

        if ($prefix === '') {
            return $this->normalizePath($path);
        }

        return $this->normalizePath($prefix . '/' . ltrim($path, '/'));
    }

    private function withNamePrefix(string $name): string
    {
        if ($this->namePrefixes === []) {
            return $name;
        }

        return implode('', $this->namePrefixes) . $name;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function formatUrl(string $path): string
    {
        return '/' . ltrim($this->normalizePath($path), '/');
    }
}
