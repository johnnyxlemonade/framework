<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Lemonade\Framework\Container\Config\ContainerConfig;
use Lemonade\Framework\Container\Definition\ClassTarget;
use Lemonade\Framework\Container\Definition\DefinitionTarget;
use Lemonade\Framework\Container\Definition\FactoryTarget;
use Lemonade\Framework\Container\Definition\InstanceTarget;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionNamedType;

final class Container implements ContainerInterface
{
    private ContainerBuilder $builder;
    private ?CompiledContainerPlan $compiledPlan = null;

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

    public function __construct(?ContainerBuilder $builder = null)
    {
        $this->builder = $builder ?? new ContainerBuilder();
    }

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function set(string $id, callable|object|string $concrete): void
    {
        $this->builder->set($id, $concrete);
        $this->definitionChanged($id);
    }

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function singleton(string $id, callable|object|string $concrete): void
    {
        $this->builder->singleton($id, $concrete);
        $this->definitionChanged($id);
    }

    public function singletonTagged(string $id, callable|object|string $concrete, string ...$tags): void
    {
        $this->singleton($id, $concrete);

        foreach ($tags as $tag) {
            $this->tag($id, $tag);
        }
    }

    public function tag(string $serviceId, string $tag): void
    {
        $this->builder->tag($serviceId, $tag);
        $this->compiledPlan = null;
    }

    public function tagged(string $tag): iterable
    {
        $normalizedTag = trim($tag);
        if ($normalizedTag === '') {
            throw new ContainerException('Service tag must not be empty.');
        }

        $serviceIds = $this->compiledPlan()->taggedServiceIds($normalizedTag);

        return $this->resolveTagged($normalizedTag, $serviceIds);
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
        return isset($this->instances[$id]) || $this->builder->hasDefinition($id);
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

        $definition = $this->compiledPlan()->definition($id);

        if ($definition === null) {
            if (!$this->classExists($id)) {
                throw new ServiceNotFoundException(sprintf(
                    'Service "%s" was not found.',
                    $id,
                ));
            }

            $this->reportAutowireFallback($id);

            return $this->build($id);
        }

        $resolved = $this->resolve($definition->target);

        if ($definition->lifetime === ServiceLifetime::Singleton) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }

    private function reportAutowireFallback(string $id): void
    {
        if (!$this->isAutowireFallbackWarningEnabled()) {
            return;
        }

        if (isset($this->reportedAutowireFallbacks[$id])) {
            return;
        }

        $this->reportedAutowireFallbacks[$id] = true;

        $message = sprintf(
            'Autowiring fallback used for "%s". Register this service explicitly in an appropriate ServiceProvider.',
            $id,
        );

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

        $instance = $this->boundInstance(ApplicationContext::class);

        return $instance instanceof ApplicationContext ? $instance : null;
    }

    private function peekContainerConfig(): ?ContainerConfig
    {
        if (
            isset($this->instances[ContainerConfig::class])
            && $this->instances[ContainerConfig::class] instanceof ContainerConfig
        ) {
            return $this->instances[ContainerConfig::class];
        }

        $instance = $this->boundInstance(ContainerConfig::class);

        return $instance instanceof ContainerConfig ? $instance : null;
    }

    private function peekLogger(): ?LoggerInterface
    {
        if (
            isset($this->instances[LoggerInterface::class])
            && $this->instances[LoggerInterface::class] instanceof LoggerInterface
        ) {
            return $this->instances[LoggerInterface::class];
        }

        $instance = $this->boundInstance(LoggerInterface::class);

        return $instance instanceof LoggerInterface ? $instance : null;
    }

    private function autowireFallbackLogger(): ?LoggerInterface
    {
        if ($this->autowireFallbackLogger instanceof LoggerInterface) {
            return $this->autowireFallbackLogger;
        }

        $this->autowireFallbackLogger = $this->diagnosticLogger ?? $this->peekLogger();

        return $this->autowireFallbackLogger;
    }

    private function resolve(DefinitionTarget $target): mixed
    {
        if ($target instanceof FactoryTarget) {
            return ($target->factory)($this);
        }

        if ($target instanceof InstanceTarget) {
            return $target->instance;
        }

        if (!$target instanceof ClassTarget || !$this->classExists($target->className)) {
            throw new ServiceNotFoundException(sprintf(
                'Service "%s" was not found.',
                $target instanceof ClassTarget ? $target->className : get_debug_type($target),
            ));
        }

        return $this->build($target->className);
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

    private function definitionChanged(string $id): void
    {
        unset($this->instances[$id]);
        $this->compiledPlan = null;
        $this->invalidateDiagnosticCacheFor($id);
    }

    private function compiledPlan(): CompiledContainerPlan
    {
        return $this->compiledPlan ??= $this->builder->compile();
    }

    private function boundInstance(string $id): ?object
    {
        $target = $this->compiledPlan()->definition($id)?->target;

        return $target instanceof InstanceTarget ? $target->instance : null;
    }

    /**
     * @param list<string> $serviceIds
     * @return \Generator<string, object>
     */
    private function resolveTagged(string $tag, array $serviceIds): \Generator
    {
        foreach ($serviceIds as $serviceId) {
            $service = $this->get($serviceId);
            if (!is_object($service)) {
                throw new ContainerException(sprintf(
                    'Tagged service "%s" for tag "%s" must resolve to an object.',
                    $serviceId,
                    $tag,
                ));
            }

            yield $serviceId => $service;
        }
    }

}
