<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Routing\Exception\MissingRouteParameterException;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Collects immutable route definitions and resolves requests after registration freezes.
 *
 * It owns route-name uniqueness and path normalization but leaves request preprocessing
 * to the dispatch boundary.
 */
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

    private string $localizedRouteNamePrefix = 'localized.';
    private string $localizedRoutePrefix = '/{locale}';
    private string $localizedLocaleParameter = 'locale';
    /**
     * @var list<string>
     */
    private array $localizedSupportedLocales = [];

    private readonly RouteCollection $collection;

    private bool $frozen = false;

    /**
     * Initializes an empty mutable route collection.
     */
    public function __construct()
    {
        $this->collection = new RouteCollection();
    }

    /**
     * Registers a GET route using the current group prefix.
     */
    public function get(string $path, ControllerAction $action): Route
    {
        return $this->map(HttpMethod::GET, $path, $action);
    }

    /**
     * Registers a POST route using the current group prefix.
     */
    public function post(string $path, ControllerAction $action): Route
    {
        return $this->map(HttpMethod::POST, $path, $action);
    }

    /**
     * Registers a PUT route using the current group prefix.
     */
    public function put(string $path, ControllerAction $action): Route
    {
        return $this->map(HttpMethod::PUT, $path, $action);
    }

    /**
     * Registers a PATCH route using the current group prefix.
     */
    public function patch(string $path, ControllerAction $action): Route
    {
        return $this->map(HttpMethod::PATCH, $path, $action);
    }

    /**
     * Registers a DELETE route using the current group prefix.
     */
    public function delete(string $path, ControllerAction $action): Route
    {
        return $this->map(HttpMethod::DELETE, $path, $action);
    }

    /**
     * Registers a HEAD route using the current group prefix.
     */
    public function head(string $path, ControllerAction $action): Route
    {
        return $this->map(HttpMethod::HEAD, $path, $action);
    }

    /**
     * Registers an OPTIONS route using the current group prefix.
     */
    public function options(string $path, ControllerAction $action): Route
    {
        return $this->map(HttpMethod::OPTIONS, $path, $action);
    }

    /**
     * Registers a named GET route and reserves its URL-generation name.
     */
    public function getNamed(string $name, string $path, ControllerAction $action): Route
    {
        return $this->mapNamed($name, HttpMethod::GET, $path, $action);
    }

    /**
     * Registers a named POST route and reserves its URL-generation name.
     */
    public function postNamed(string $name, string $path, ControllerAction $action): Route
    {
        return $this->mapNamed($name, HttpMethod::POST, $path, $action);
    }

    /**
     * Registers a named PUT route and reserves its URL-generation name.
     */
    public function putNamed(string $name, string $path, ControllerAction $action): Route
    {
        return $this->mapNamed($name, HttpMethod::PUT, $path, $action);
    }

    /**
     * Registers a named PATCH route and reserves its URL-generation name.
     */
    public function patchNamed(string $name, string $path, ControllerAction $action): Route
    {
        return $this->mapNamed($name, HttpMethod::PATCH, $path, $action);
    }

    /**
     * Registers a named DELETE route and reserves its URL-generation name.
     */
    public function deleteNamed(string $name, string $path, ControllerAction $action): Route
    {
        return $this->mapNamed($name, HttpMethod::DELETE, $path, $action);
    }

    /**
     * Registers a named HEAD route and reserves its URL-generation name.
     */
    public function headNamed(string $name, string $path, ControllerAction $action): Route
    {
        return $this->mapNamed($name, HttpMethod::HEAD, $path, $action);
    }

    /**
     * Registers a named OPTIONS route and reserves its URL-generation name.
     */
    public function optionsNamed(string $name, string $path, ControllerAction $action): Route
    {
        return $this->mapNamed($name, HttpMethod::OPTIONS, $path, $action);
    }

    /**
     * Adds one route definition and applies constraints configured for localized groups.
     */
    public function map(HttpMethod|string $method, string $path, ControllerAction $action): Route
    {
        $this->assertMutable();

        $methodName = $this->normalizeMethod($method);
        $normalizedPath = $this->withGroupPrefix($path);

        $route = new Route(
            method: $methodName,
            path: $normalizedPath,
            controllerAction: $action,
            assertMutable: fn(): bool => $this->assertMutable(),
            registerName: function (Route $route, string $name): void {
                $this->registerRouteName($route, $name);
            },
        );

        $this->applyLocalizedRouteConstraints($route);

        $this->collection->add($route);
        $this->routeList[] = $route;

        return $route;
    }

    /**
     * Adds one named route after rejecting a duplicate effective name.
     */
    public function mapNamed(string $name, HttpMethod|string $method, string $path, ControllerAction $action): Route
    {
        $this->assertMutable();

        $resolvedName = $this->withNamePrefix($name);

        $this->assertRouteNameAvailable($resolvedName);

        $route = $this->map($method, $path, $action);

        $route->name($resolvedName);

        $this->namedRoutes[$resolvedName] = $this->formatUrl($route->path());

        return $route;
    }

    /**
     * @param callable(self): void $builder
     */
    /**
     * Runs a registration callback with a temporary path prefix and returns its routes.
     */
    public function group(string $prefix, callable $builder): RouteGroup
    {
        $this->assertMutable();

        $before = count($this->routeList);

        $this->groupPrefixes[] = RoutePathNormalizer::normalize($prefix);

        try {
            $builder($this);
        } finally {
            array_pop($this->groupPrefixes);
        }

        return new RouteGroup(
            array_slice($this->routeList, $before),
        );
    }

    /**
     * @param callable(self): void $builder
     */
    /**
     * Registers parallel plain and locale-prefixed variants for the callback's routes.
     */
    public function localizedGroup(callable $builder): LocalizedRouteGroup
    {
        $this->assertMutable();

        $beforePlain = count($this->routeList);

        $builder($this);
        $plainRoutes = array_slice($this->routeList, $beforePlain);

        $localizedGroup = $this->group($this->localizedRoutePrefix, function (Router $router) use ($builder): void {
            $this->namePrefixes[] = $this->localizedRouteNamePrefix;

            try {
                $builder($router);
            } finally {
                array_pop($this->namePrefixes);
            }
        });

        return new LocalizedRouteGroup(
            plainRoutes: $plainRoutes,
            localizedRoutes: $localizedGroup->routes(),
        );
    }

    /**
     * @param list<string> $supportedLocales
     */
    /**
     * Configures the static locale-prefix convention used by future localized groups.
     *
     * It must run before route registration is frozen.
     *
     * @param array<mixed> $supportedLocales
     */
    public function configureLocalizedRoutes(
        string $routeNamePrefix = 'localized.',
        ?string $routePrefix = null,
        string $localeParameter = 'locale',
        array $supportedLocales = [],
    ): void {
        $this->assertMutable();

        $localeParameter = trim($localeParameter);
        $this->localizedLocaleParameter = $localeParameter !== '' ? $localeParameter : 'locale';
        $this->localizedRouteNamePrefix = $routeNamePrefix;
        $this->localizedSupportedLocales = $this->normalizeSupportedLocales($supportedLocales);

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
    /**
     * Generates a path for a named route and appends unused parameters as a query string.
     *
     * @param array<string, scalar|null> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw RouteNotFoundException::forName($name);
        }

        return $this->buildUrl($this->namedRoutes[$name], $params);
    }

    /**
     * Prevents further route and route-configuration mutation.
     */
    /**
     * Prevents further route and localized-route configuration changes.
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    /**
     * Reports whether route registration has become immutable.
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Matches the request method against its URI path or a dispatch-only path override.
     *
     * The override changes only route selection; request metadata and the URI reported
     * by a missing-route exception remain those of the original request.
     */
    public function match(ServerRequestInterface $request, ?string $dispatchPath = null): RouteMatch
    {
        $method = strtoupper($request->getMethod());
        $path = RoutePathNormalizer::normalize($dispatchPath ?? $request->getUri()->getPath());

        $candidateMethods = $method === HttpMethod::HEAD->value
            ? [HttpMethod::HEAD->value, HttpMethod::GET->value]
            : [$method];
        foreach ($candidateMethods as $candidateMethod) {
            $match = $this->collection->match($candidateMethod, $path);
            if ($match !== null) {
                return $match;
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
    /**
     * Lists normalized HTTP methods that have a route for the supplied path.
     *
     * @return list<string>
     */
    public function allowedMethodsForPath(string $path): array
    {
        $normalizedPath = RoutePathNormalizer::normalize($path);
        $allowed = $this->collection->allowedMethodsForPath($normalizedPath);

        return RouteCollection::sortMethods($allowed);
    }

    /**
     * Reports whether one method has an explicitly registered route for the path.
     */
    public function hasExplicitRouteForPath(HttpMethod|string $method, string $path): bool
    {
        return $this->collection->hasExplicitRouteForPath($method, $path);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    /**
     * Substitutes path parameters and serializes remaining values into a query string.
     *
     * @param array<string, scalar|null> $params
     */
    private function buildUrl(string $path, array $params): string
    {
        $used = [];

        $url = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(:any)?\}/',
            function (array $matches) use ($params, &$used): string {
                $key = $matches[1];
                $isWildcard = isset($matches[2]);

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

                return $this->encodeRouteParameter(
                    key: $key,
                    value: (string) $value,
                    wildcard: $isWildcard,
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

    /**
     * Encodes one route value according to whether its placeholder spans segments.
     */
    private function encodeRouteParameter(string $key, string $value, bool $wildcard): string
    {
        if ($wildcard) {
            return $this->encodeWildcardRouteParameter($key, $value);
        }

        return $this->encodeSimpleRouteParameter($key, $value);
    }

    /**
     * Encodes a single-segment route value and rejects embedded separators.
     */
    private function encodeSimpleRouteParameter(string $key, string $value): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException(sprintf(
                'Route parameter "%s" must resolve to exactly one non-empty logical path segment.',
                $key,
            ));
        }

        return rawurlencode($value);
    }

    /**
     * Encodes each non-empty segment of a wildcard route value independently.
     */
    private function encodeWildcardRouteParameter(string $key, string $value): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException(sprintf(
                'Wildcard route parameter "%s" must resolve to one or more non-empty logical path segments.',
                $key,
            ));
        }

        $segments = explode('/', $value);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new \InvalidArgumentException(sprintf(
                    'Wildcard route parameter "%s" must not contain empty logical path segments.',
                    $key,
                ));
            }
        }

        return implode(
            '/',
            array_map(
                static fn(string $segment): string => rawurlencode($segment),
                $segments,
            ),
        );
    }

    /**
     * Converts an enum or arbitrary method input to its uppercase routing form.
     */
    private function normalizeMethod(HttpMethod|string $method): string
    {
        return $method instanceof HttpMethod
            ? $method->value
            : strtoupper($method);
    }

    /**
     * Applies all active registration-group prefixes to a route path.
     */
    private function withGroupPrefix(string $path): string
    {
        $prefix = implode('', $this->groupPrefixes);

        if ($prefix === '') {
            return RoutePathNormalizer::normalize($path);
        }

        return RoutePathNormalizer::normalize($prefix . '/' . ltrim($path, '/'));
    }

    /**
     * Applies active localized name prefixes to a route name.
     */
    private function withNamePrefix(string $name): string
    {
        if ($this->namePrefixes === []) {
            return $name;
        }

        return implode('', $this->namePrefixes) . $name;
    }

    /**
     * Persists a route's generated URL template after duplicate-name validation.
     */
    private function registerRouteName(Route $route, string $name): void
    {
        $this->assertMutable();
        $this->assertRouteNameAvailable($name);

        $this->namedRoutes[$name] = $this->formatUrl($route->path());
    }

    /**
     * Rejects reuse of a name that already identifies a registered route.
     */
    private function assertRouteNameAvailable(string $name): void
    {
        if (isset($this->namedRoutes[$name])) {
            throw new \LogicException(sprintf(
                'Named route "%s" is already registered as "%s".',
                $name,
                $this->namedRoutes[$name],
            ));
        }
    }

    /**
     * Rejects route mutations once the router has entered its immutable runtime state.
     */
    private function assertMutable(): bool
    {
        if ($this->frozen) {
            throw new \LogicException('Router is frozen.');
        }

        return true;
    }

    /**
     * Produces the leading-slash URL template stored for named-route generation.
     */
    private function formatUrl(string $path): string
    {
        return '/' . ltrim(RoutePathNormalizer::normalize($path), '/');
    }

    /**
     * Limits the configured locale placeholder to the supported static locale set.
     */
    private function applyLocalizedRouteConstraints(Route $route): void
    {
        if ($this->localizedSupportedLocales === []) {
            return;
        }

        $segments = array_values(array_filter(
            explode('/', trim($route->path(), '/')),
            static fn(string $segment): bool => $segment !== '',
        ));

        foreach ($segments as $segment) {
            if ($segment === '{' . $this->localizedLocaleParameter . '}') {
                $route->constrainParameter(
                    $this->localizedLocaleParameter,
                    $this->localizedSupportedLocales,
                );
                return;
            }
        }
    }

    /**
     * @param array<mixed> $supportedLocales
     * @return list<string>
     */
    /**
     * Removes invalid and duplicate scalar locale values while preserving order.
     *
     * @param array<mixed> $supportedLocales
     * @return list<string>
     */
    private function normalizeSupportedLocales(array $supportedLocales): array
    {
        $normalized = [];

        foreach ($supportedLocales as $locale) {
            if (!is_scalar($locale)) {
                continue;
            }

            $value = trim((string) $locale);

            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }
}
