<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Closure;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;

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
    private ?LoggerInterface $diagnosticLogger = null;

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
    }

    public function setDiagnosticLogger(?LoggerInterface $logger): void
    {
        $this->diagnosticLogger = $logger;
    }

    /**
     * @param class-string|string $id
     */
    public function has(string $id): bool
    {
        return $this->isBound($id) || class_exists($id);
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

        if (!isset($this->bindings[$id]) && !class_exists($id)) {
            throw new ServiceNotFoundException(sprintf(
                'Service "%s" was not found.',
                $id,
            ));
        }

        if (!isset($this->bindings[$id]) && class_exists($id)) {
            $this->reportAutowireFallback($id);
        }

        $binding = $this->bindings[$id] ?? [
            'concrete' => $id,
            'singleton' => false,
        ];

        $resolved = $this->resolve($binding['concrete']);

        if ($binding['singleton']) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }

    private function reportAutowireFallback(string $id): void
    {
        if (!$this->shouldReportAutowireFallback($id)) {
            return;
        }

        if (isset($this->reportedAutowireFallbacks[$id])) {
            return;
        }

        if (!$this->isAutowireFallbackWarningEnabled()) {
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

        $logger = $this->diagnosticLogger ?? $this->peekLogger();
        if ($logger !== null && !$logger instanceof NullLogger) {
            $logger->warning($message, [
                'service' => $id,
                'source' => 'container.autowire_fallback',
            ]);
            return;
        }

        // Dev fallback when logger is not available yet.
        error_log('[Lemonade][Container] ' . $message);
    }

    private function shouldReportAutowireFallback(string $id): bool
    {
        if (str_starts_with($id, 'App\\')) {
            return $this->shouldReportApplicationAutowireFallback($id);
        }

        if (str_starts_with($id, 'Lemonade\\Framework\\')) {
            return $this->shouldReportFrameworkAutowireFallback($id);
        }

        return false;
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
        $config = $this->peekConfig();

        if ($config instanceof Config) {
            return $config->bool('app.container.autowire_fallback_warning', false);
        }

        $context = $this->peekContext();

        if ($context instanceof ApplicationContext) {
            return $context->isDevelopment();
        }

        return false;
    }

    private function peekContext(): ?ApplicationContext
    {
        if (isset($this->instances[ApplicationContext::class]) && $this->instances[ApplicationContext::class] instanceof ApplicationContext) {
            return $this->instances[ApplicationContext::class];
        }

        $binding = $this->bindings[ApplicationContext::class]['concrete'] ?? null;
        return $binding instanceof ApplicationContext ? $binding : null;
    }

    private function peekConfig(): ?Config
    {
        if (isset($this->instances[Config::class]) && $this->instances[Config::class] instanceof Config) {
            return $this->instances[Config::class];
        }

        $binding = $this->bindings[Config::class]['concrete'] ?? null;
        return $binding instanceof Config ? $binding : null;
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

        if (!class_exists($concrete)) {
            throw new ServiceNotFoundException(sprintf(
                'Service "%s" was not found.',
                $concrete,
            ));
        }

        return $this->build($concrete);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     * @return T
     */
    private function build(string $className): object
    {
        $reflection = new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf(
                'Class "%s" is not instantiable.',
                $className,
            ));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveConstructorParameter(
                className: $className,
                parameterName: $parameter->getName(),
                type: $parameter->getType(),
                hasDefaultValue: $parameter->isDefaultValueAvailable(),
                defaultValue: $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null,
            );
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveConstructorParameter(
        string $className,
        string $parameterName,
        ?ReflectionType $type,
        bool $hasDefaultValue,
        mixed $defaultValue,
    ): mixed {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            if ($hasDefaultValue) {
                return $defaultValue;
            }

            throw new ContainerException(sprintf(
                'Cannot autowire "%s::$%s". Parameter has no resolvable class type.',
                $className,
                $parameterName,
            ));
        }

        $dependency = $type->getName();

        if (!class_exists($dependency) && !interface_exists($dependency)) {
            if ($hasDefaultValue) {
                return $defaultValue;
            }

            throw new ContainerException(sprintf(
                'Cannot autowire "%s::$%s". Dependency "%s" does not exist.',
                $className,
                $parameterName,
                $dependency,
            ));
        }

        if (interface_exists($dependency) && !$this->isBound($dependency)) {
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
}
