<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Closure;
use Lemonade\Framework\Container\Config\ContainerConfig;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionNamedType;

final class Container implements ContainerInterface
{
    /**
     * @var array<string, array{
     *     concrete: callable(ContainerInterface):mixed|object|string,
     *     singleton: bool
     * }>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, true>
     */
    private array $reportedAutowireFallbacks = [];

    /**
     * @var array<string, bool>
     */
    private array $autowireFallbackReportable = [];

    /**
     * @var array<string, bool>
     */
    private array $classExistenceCache = [];

    /**
     * @var array<string, bool>
     */
    private array $interfaceExistenceCache = [];

    /**
     * @var array<string, array{
     *     reflection: ReflectionClass<object>,
     *     hasConstructor: bool,
     *     parameters: list<array{
     *         name: string,
     *         kind: 'class'|'interface'|'unresolvable',
     *         dependency: string|null,
     *         hasDefaultValue: bool,
     *         defaultValue: mixed
     *     }>
     * }>
     */
    private array $buildPlans = [];

    /**
     * @var list<string>
     */
    private array $resolutionStack = [];

    private ?LoggerInterface $diagnosticLogger = null;
    private ?LoggerInterface $autowireFallbackLogger = null;
    private ?bool $autowireFallbackWarningEnabled = null;

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function set(string $id, callable|object|string $concrete): void
    {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'singleton' => false,
        ];

        unset($this->instances[$id]);
        $this->invalidateDiagnosticCacheFor($id);
    }

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function singleton(string $id, callable|object|string $concrete): void
    {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'singleton' => true,
        ];

        unset($this->instances[$id]);
        $this->invalidateDiagnosticCacheFor($id);
    }

    public function setDiagnosticLogger(?LoggerInterface $logger): void
    {
        $this->diagnosticLogger = $logger;
        $this->autowireFallbackLogger = null;
    }

    /**
     * @param class-string|string $id
     */
    public function has(string $id): bool
    {
        return $this->isBound($id) || $this->classExists($id);
    }

    /**
     * @param class-string|string $id
     */
    public function isBound(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $binding = $this->bindings[$id] ?? null;

        if ($binding === null) {
            if (!$this->classExists($id)) {
                throw new ServiceNotFoundException(sprintf(
                    'Service "%s" was not found.',
                    $id,
                ));
            }

            $this->reportAutowireFallback($id);

            return $this->build($id);
        }

        $resolved = $this->resolve($binding['concrete']);

        if ($binding['singleton']) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }

    private function reportAutowireFallback(string $id): void
    {
        if (!$this->isAutowireFallbackWarningEnabled()) {
            return;
        }

        if (!$this->shouldReportAutowireFallback($id)) {
            return;
        }

        if (isset($this->reportedAutowireFallbacks[$id])) {
            return;
        }

        $this->reportedAutowireFallbacks[$id] = true;

        $message = sprintf(
            'Autowiring fallback used for "%s". Register this service explicitly in the appropriate ServiceProvider (for app services usually App\\Providers\\AppServiceProvider).',
            $id,
        );

        if (str_starts_with($id, 'App\\')) {
            $message = sprintf(
                'Autowiring fallback used for "%s". Register this service explicitly in App\\Providers\\AppServiceProvider or another application ServiceProvider.',
                $id,
            );
        } elseif (str_starts_with($id, 'Lemonade\\Framework\\')) {
            $message = sprintf(
                'Autowiring fallback used for "%s". Register this service explicitly in the appropriate framework ServiceProvider.',
                $id,
            );
        }

        $logger = $this->autowireFallbackLogger();
        if ($logger !== null && !$logger instanceof NullLogger) {
            $logger->warning($message, [
                'service' => $id,
                'source' => 'container.autowire_fallback',
            ]);

            return;
        }

        error_log('[Lemonade][Container] ' . $message);
    }

    private function shouldReportAutowireFallback(string $id): bool
    {
        if (array_key_exists($id, $this->autowireFallbackReportable)) {
            return $this->autowireFallbackReportable[$id];
        }

        $shouldReport = false;

        if (str_starts_with($id, 'App\\')) {
            $shouldReport = $this->shouldReportApplicationAutowireFallback($id);
        } elseif (str_starts_with($id, 'Lemonade\\Framework\\')) {
            $shouldReport = $this->shouldReportFrameworkAutowireFallback($id);
        }

        $this->autowireFallbackReportable[$id] = $shouldReport;

        return $shouldReport;
    }

    private function shouldReportApplicationAutowireFallback(string $id): bool
    {
        return str_contains($id, '\\Services\\')
            || str_contains($id, '\\Models\\')
            || str_contains($id, '\\Documentation\\')
            || str_contains($id, '\\Auth\\')
            || str_contains($id, '\\Routing\\')
            || str_ends_with($id, 'Service')
            || str_ends_with($id, 'Model')
            || str_ends_with($id, 'Catalog')
            || str_ends_with($id, 'Authenticator');
    }

    private function shouldReportFrameworkAutowireFallback(string $id): bool
    {
        return str_ends_with($id, 'Service')
            || str_ends_with($id, 'Manager')
            || str_ends_with($id, 'Registry')
            || str_ends_with($id, 'Compiler')
            || str_ends_with($id, 'Middleware');
    }

    private function isAutowireFallbackWarningEnabled(): bool
    {
        if ($this->autowireFallbackWarningEnabled !== null) {
            return $this->autowireFallbackWarningEnabled;
        }

        $config = $this->peekContainerConfig();

        if ($config instanceof ContainerConfig) {
            $this->autowireFallbackWarningEnabled = $config->autowireFallbackWarning;

            return $this->autowireFallbackWarningEnabled;
        }

        $context = $this->peekContext();

        if ($context instanceof ApplicationContext) {
            $this->autowireFallbackWarningEnabled = $context->isDevelopment();

            return $this->autowireFallbackWarningEnabled;
        }

        $this->autowireFallbackWarningEnabled = false;

        return false;
    }

    private function peekContext(): ?ApplicationContext
    {
        if (
            isset($this->instances[ApplicationContext::class])
            && $this->instances[ApplicationContext::class] instanceof ApplicationContext
        ) {
            return $this->instances[ApplicationContext::class];
        }

        $binding = $this->bindings[ApplicationContext::class]['concrete'] ?? null;

        return $binding instanceof ApplicationContext ? $binding : null;
    }

    private function peekContainerConfig(): ?ContainerConfig
    {
        if (
            isset($this->instances[ContainerConfig::class])
            && $this->instances[ContainerConfig::class] instanceof ContainerConfig
        ) {
            return $this->instances[ContainerConfig::class];
        }

        $binding = $this->bindings[ContainerConfig::class]['concrete'] ?? null;

        return $binding instanceof ContainerConfig ? $binding : null;
    }

    private function peekLogger(): ?LoggerInterface
    {
        if (
            isset($this->instances[LoggerInterface::class])
            && $this->instances[LoggerInterface::class] instanceof LoggerInterface
        ) {
            return $this->instances[LoggerInterface::class];
        }

        $binding = $this->bindings[LoggerInterface::class]['concrete'] ?? null;

        return $binding instanceof LoggerInterface ? $binding : null;
    }

    private function autowireFallbackLogger(): ?LoggerInterface
    {
        if ($this->autowireFallbackLogger instanceof LoggerInterface) {
            return $this->autowireFallbackLogger;
        }

        $this->autowireFallbackLogger = $this->diagnosticLogger ?? $this->peekLogger();

        return $this->autowireFallbackLogger;
    }

    /**
     * @param callable(ContainerInterface):mixed|object|string $concrete
     */
    private function resolve(callable|object|string $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        if (is_callable($concrete) && !is_string($concrete)) {
            return $concrete($this);
        }

        if (is_object($concrete)) {
            return $concrete;
        }

        if (!$this->classExists($concrete)) {
            throw new ServiceNotFoundException(sprintf(
                'Service "%s" was not found.',
                $concrete,
            ));
        }

        return $this->build($concrete);
    }

    private function build(string $className): object
    {
        if (in_array($className, $this->resolutionStack, true)) {
            $chain = [...$this->resolutionStack, $className];

            throw new ContainerException(sprintf(
                'Circular dependency detected: %s',
                implode(' -> ', $chain),
            ));
        }

        $this->resolutionStack[] = $className;

        try {
            $plan = $this->buildPlan($className);

            if (!$plan['hasConstructor']) {
                return $plan['reflection']->newInstance();
            }

            $arguments = [];

            foreach ($plan['parameters'] as $parameter) {
                $arguments[] = $this->resolveConstructorParameter(
                    className: $className,
                    parameterName: $parameter['name'],
                    kind: $parameter['kind'],
                    dependency: $parameter['dependency'],
                    hasDefaultValue: $parameter['hasDefaultValue'],
                    defaultValue: $parameter['defaultValue'],
                );
            }

            return $plan['reflection']->newInstanceArgs($arguments);
        } finally {
            array_pop($this->resolutionStack);
        }
    }

    /**
     * @return array{
     *     reflection: ReflectionClass<object>,
     *     hasConstructor: bool,
     *     parameters: list<array{
     *         name: string,
     *         kind: 'class'|'interface'|'unresolvable',
     *         dependency: string|null,
     *         hasDefaultValue: bool,
     *         defaultValue: mixed
     *     }>
     * }
     */
    private function buildPlan(string $className): array
    {
        if (isset($this->buildPlans[$className])) {
            return $this->buildPlans[$className];
        }

        if (!class_exists($className)) {
            throw new ServiceNotFoundException(sprintf(
                'Service "%s" was not found.',
                $className,
            ));
        }

        $reflection = new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf(
                'Class "%s" is not instantiable.',
                $className,
            ));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $plan = [
                'reflection' => $reflection,
                'hasConstructor' => false,
                'parameters' => [],
            ];

            $this->buildPlans[$className] = $plan;

            return $plan;
        }

        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $kind = 'unresolvable';
            $dependency = null;

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencyName = $type->getName();

                if ($this->interfaceExists($dependencyName)) {
                    $kind = 'interface';
                    $dependency = $dependencyName;
                } elseif ($this->classExists($dependencyName)) {
                    $kind = 'class';
                    $dependency = $dependencyName;
                }
            }

            $parameters[] = [
                'name' => $parameter->getName(),
                'kind' => $kind,
                'dependency' => $dependency,
                'hasDefaultValue' => $parameter->isDefaultValueAvailable(),
                'defaultValue' => $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null,
            ];
        }

        $plan = [
            'reflection' => $reflection,
            'hasConstructor' => true,
            'parameters' => $parameters,
        ];

        $this->buildPlans[$className] = $plan;

        return $plan;
    }

    private function resolveConstructorParameter(
        string $className,
        string $parameterName,
        string $kind,
        ?string $dependency,
        bool $hasDefaultValue,
        mixed $defaultValue,
    ): mixed {
        if ($kind === 'unresolvable' || $dependency === null) {
            if ($hasDefaultValue) {
                return $defaultValue;
            }

            throw new ContainerException(sprintf(
                'Cannot autowire "%s::$%s". Parameter has no resolvable class type.',
                $className,
                $parameterName,
            ));
        }

        if ($kind === 'interface' && !$this->isBound($dependency)) {
            if ($hasDefaultValue) {
                return $defaultValue;
            }

            throw new ServiceNotFoundException(sprintf(
                'Cannot autowire "%s::$%s". Interface "%s" has no container binding.',
                $className,
                $parameterName,
                $dependency,
            ));
        }

        return $this->get($dependency);
    }

    private function classExists(string $className): bool
    {
        if (array_key_exists($className, $this->classExistenceCache)) {
            return $this->classExistenceCache[$className];
        }

        $this->classExistenceCache[$className] = class_exists($className);

        return $this->classExistenceCache[$className];
    }

    private function interfaceExists(string $interfaceName): bool
    {
        if (array_key_exists($interfaceName, $this->interfaceExistenceCache)) {
            return $this->interfaceExistenceCache[$interfaceName];
        }

        $this->interfaceExistenceCache[$interfaceName] = interface_exists($interfaceName);

        return $this->interfaceExistenceCache[$interfaceName];
    }

    private function invalidateDiagnosticCacheFor(string $id): void
    {
        if ($id === ContainerConfig::class || $id === ApplicationContext::class) {
            $this->autowireFallbackWarningEnabled = null;
        }

        if ($id === LoggerInterface::class) {
            $this->autowireFallbackLogger = null;
        }
    }
}
