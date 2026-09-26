<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Lemonade\Framework\Container\Config\ContainerConfig;
use Lemonade\Framework\Container\Definition\ClassTarget;
use Lemonade\Framework\Container\Definition\DefinitionTarget;
use Lemonade\Framework\Container\Definition\FactoryTarget;
use Lemonade\Framework\Container\Definition\InstanceTarget;
use Lemonade\Framework\Container\Exception\AliasTargetNotFoundException;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\InvalidContextualBindingException;
use Lemonade\Framework\Container\Exception\InvalidServiceDecoratorException;
use Lemonade\Framework\Container\Exception\ScopedServiceRequestedFromRootException;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use Lemonade\Framework\Container\Exception\SingletonDependsOnScopedServiceException;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionNamedType;

final class Container implements ContainerInterface, ContainerBuilderInterface, ScopeFactoryInterface
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
    private array $classResolutionStack = [];

    /**
     * Canonical service IDs currently being resolved through get().
     *
     * @var list<string>
     */
    private array $serviceResolutionStack = [];

    /** @var list<ServiceLifetime> */
    private array $serviceLifetimeStack = [];

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

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function scoped(string $id, callable|object|string $concrete): void
    {
        $this->builder->scoped($id, $concrete);
        $this->definitionChanged($id);
    }

    public function beginScope(ScopeKind $kind): ScopedContainerInterface
    {
        return new ScopedContainer($this, $kind);
    }

    public function transient(string $id, callable|object|string $concrete): void
    {
        $this->set($id, $concrete);
    }

    public function instance(string $id, object $instance): void
    {
        $this->builder->instance($id, $instance);
        $this->definitionChanged($id);
    }

    /**
     * The concrete container implements the builder contract used by definition providers.
     * This method intentionally is not added to the legacy ContainerInterface.
     *
     * @param non-empty-string $alias
     * @param non-empty-string $target
     */
    public function alias(string $alias, string $target): void
    {
        $this->builder->alias($alias, $target);
        $this->compiledPlan = null;
    }

    /**
     * The concrete container implements the builder contract used by definition providers.
     * This method intentionally is not added to the legacy ContainerInterface.
     *
     * @param class-string|non-empty-string $serviceId
     * @param class-string<ServiceDecoratorInterface>|callable(ContainerInterface, mixed):mixed $decorator
     */
    public function decorate(string $serviceId, string|callable $decorator, int $priority = 0): void
    {
        $canonicalId = $this->compiledPlan()->canonicalId($serviceId);
        $this->builder->decorate($serviceId, $decorator, $priority);
        $this->definitionChanged($canonicalId);
    }

    /**
     * The concrete container implements the builder contract used by definition providers.
     * This method intentionally is not added to the legacy ContainerInterface.
     *
     * @param class-string|string $consumer
     */
    public function when(string $consumer): ContextualBindingBuilder
    {
        return $this->builder->contextualBindingBuilder($consumer, function (): void {
            $this->instances = [];
            $this->compiledPlan = null;
        });
    }

    public function compile(): CompiledContainerPlan
    {
        return $this->compiledPlan();
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

    /** @return iterable<string, object> */
    public function tagged(string $tag): iterable
    {
        return $this->taggedFor($this, $tag);
    }

    /** @return iterable<string, object> */
    public function taggedInScope(ScopedContainer $scope, string $tag): iterable
    {
        return $this->taggedFor($scope, $tag);
    }

    /** @return iterable<string, object> */
    private function taggedFor(ContainerInterface $runtimeContainer, string $tag): iterable
    {
        $normalizedTag = trim($tag);
        if ($normalizedTag === '') {
            throw new ContainerException('Service tag must not be empty.');
        }

        $serviceIds = $this->compiledPlan()->taggedServiceIds($normalizedTag);

        return $this->resolveTagged($runtimeContainer, $normalizedTag, $serviceIds);
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
        $plan = $this->compiledPlan();
        $canonicalId = $plan->canonicalId($id);

        return $plan->hasDefinition($canonicalId) || $this->classExists($canonicalId);
    }

    /**
     * @param class-string|string $id
     */
    public function isBound(string $id): bool
    {
        $plan = $this->compiledPlan();
        $canonicalId = $plan->canonicalId($id);

        return isset($this->instances[$canonicalId]) || $plan->hasDefinition($canonicalId) || $plan->hasAlias($id);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        return $this->resolveService($this, $id);
    }

    public function getInScope(ScopedContainer $scope, string $id): mixed
    {
        return $this->resolveService($scope, $id);
    }

    public function assertScopeLocalServiceCanResolve(string $id): void
    {
        if (!in_array(ServiceLifetime::Singleton, $this->serviceLifetimeStack, true)) {
            return;
        }

        $chain = [...$this->serviceResolutionStack, $id];

        throw new SingletonDependsOnScopedServiceException(sprintf(
            'Singleton service resolution cannot depend on scope-local service "%s": %s',
            $id,
            implode(' -> ', $chain),
        ));
    }

    private function resolveService(ContainerInterface $runtimeContainer, string $id): mixed
    {
        $plan = $this->compiledPlan();
        $canonicalId = $plan->canonicalId($id);

        $definition = $plan->definition($canonicalId);

        if ($definition?->lifetime === ServiceLifetime::Singleton && isset($this->instances[$canonicalId])) {
            return $this->instances[$canonicalId];
        }

        $scope = $runtimeContainer instanceof ScopedContainer ? $runtimeContainer : null;
        if ($definition?->lifetime === ServiceLifetime::Scoped) {
            $scope = $this->scopedContainerFor($canonicalId, $scope);

            if ($scope->hasInstance($canonicalId)) {
                return $scope->instance($canonicalId);
            }
        }

        $this->beginServiceResolution(
            $canonicalId,
            $definition === null ? ServiceLifetime::Transient : $definition->lifetime,
        );

        try {
            if ($definition === null) {
                if (!$this->classExists($canonicalId)) {
                    if ($plan->hasAlias($id)) {
                        throw new AliasTargetNotFoundException(sprintf(
                            'Service alias "%s" resolves to "%s", but the target service was not found.',
                            $id,
                            $canonicalId,
                        ));
                    }

                    throw new ServiceNotFoundException(sprintf(
                        'Service "%s" was not found.',
                        $canonicalId,
                    ));
                }

                $this->reportAutowireFallback($canonicalId);

                return $this->build($canonicalId, $runtimeContainer);
            }

            $resolutionContainer = $definition->lifetime === ServiceLifetime::Singleton
                ? $this
                : $runtimeContainer;
            $resolved = $this->resolve($definition->target, $resolutionContainer);
            $resolved = $this->applyDecorators($resolutionContainer, $plan->decorators($canonicalId), $resolved);

            if ($definition->lifetime === ServiceLifetime::Singleton) {
                $this->instances[$canonicalId] = $resolved;
            } elseif ($definition->lifetime === ServiceLifetime::Scoped) {
                assert($scope instanceof ScopedContainer);
                $scope->storeInstance($canonicalId, $resolved);
            }

            return $resolved;
        } finally {
            array_pop($this->serviceResolutionStack);
            array_pop($this->serviceLifetimeStack);
        }
    }

    private function beginServiceResolution(string $canonicalId, ServiceLifetime $lifetime): void
    {
        if (in_array($canonicalId, $this->serviceResolutionStack, true)) {
            $chain = [...$this->serviceResolutionStack, $canonicalId];

            throw new ContainerException(sprintf(
                'Circular dependency detected: %s',
                implode(' -> ', $chain),
            ));
        }

        $this->serviceResolutionStack[] = $canonicalId;
        $this->serviceLifetimeStack[] = $lifetime;
    }

    private function scopedContainerFor(string $canonicalId, ?ScopedContainer $scope): ScopedContainer
    {
        if (in_array(ServiceLifetime::Singleton, $this->serviceLifetimeStack, true)) {
            $chain = [...$this->serviceResolutionStack, $canonicalId];

            throw new SingletonDependsOnScopedServiceException(sprintf(
                'Singleton service resolution cannot depend on scoped service "%s": %s',
                $canonicalId,
                implode(' -> ', $chain),
            ));
        }

        if ($scope === null) {
            throw new ScopedServiceRequestedFromRootException(sprintf(
                'Scoped service "%s" can only be resolved from an active scope.',
                $canonicalId,
            ));
        }

        return $scope;
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

    private function resolve(DefinitionTarget $target, ContainerInterface $runtimeContainer): mixed
    {
        if ($target instanceof FactoryTarget) {
            return ($target->factory)($runtimeContainer);
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

        return $this->build($target->className, $runtimeContainer);
    }

    /**
     * @param list<ServiceDecorator> $decorators
     */
    private function applyDecorators(ContainerInterface $runtimeContainer, array $decorators, mixed $inner): mixed
    {
        foreach ($decorators as $decorator) {
            if ($decorator->decorator instanceof \Closure) {
                $inner = ($decorator->decorator)($runtimeContainer, $inner);

                continue;
            }

            $instance = $this->build($decorator->decorator, $runtimeContainer);
            if (!$instance instanceof ServiceDecoratorInterface) {
                throw new InvalidServiceDecoratorException(sprintf(
                    'Service decorator class "%s" must implement %s.',
                    $decorator->decorator,
                    ServiceDecoratorInterface::class,
                ));
            }

            $inner = $instance->decorate($inner);
        }

        return $inner;
    }

    private function build(string $className, ContainerInterface $runtimeContainer): object
    {
        if (in_array($className, $this->classResolutionStack, true)) {
            $chain = [...$this->classResolutionStack, $className];

            throw new ContainerException(sprintf(
                'Circular dependency detected: %s',
                implode(' -> ', $chain),
            ));
        }

        $this->classResolutionStack[] = $className;

        try {
            $plan = $this->buildPlan($className);

            if (!$plan['hasConstructor']) {
                return $plan['reflection']->newInstance();
            }

            $arguments = [];

            foreach ($plan['parameters'] as $parameter) {
                $arguments[] = $this->resolveConstructorParameter(
                    runtimeContainer: $runtimeContainer,
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
            array_pop($this->classResolutionStack);
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
        ContainerInterface $runtimeContainer,
        string $className,
        string $parameterName,
        string $kind,
        ?string $dependency,
        bool $hasDefaultValue,
        mixed $defaultValue,
    ): mixed {
        $contextualBinding = $this->compiledPlan()->contextualParameter($className, $parameterName);
        if (!$contextualBinding instanceof ContextualBinding && $dependency !== null) {
            $contextualBinding = $this->compiledPlan()->contextualDependency($className, $dependency);
        }

        if ($contextualBinding instanceof ContextualBinding) {
            return $this->resolveContextualBinding($runtimeContainer, $contextualBinding);
        }

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

        if ($kind === 'interface' && !$runtimeContainer->isBound($dependency)) {
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

        return $runtimeContainer->get($dependency);
    }

    private function resolveContextualBinding(ContainerInterface $runtimeContainer, ContextualBinding $binding): mixed
    {
        if ($binding->value->kind === 'service') {
            if (!is_string($binding->value->value)) {
                throw new InvalidContextualBindingException('Contextual service binding must contain a service ID.');
            }

            return $runtimeContainer->get($binding->value->value);
        }

        if ($binding->value->kind === 'factory') {
            if (!$binding->value->value instanceof \Closure) {
                throw new InvalidContextualBindingException('Contextual factory binding must contain a callable factory.');
            }

            return ($binding->value->value)($runtimeContainer);
        }

        return $binding->value->value;
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
    private function resolveTagged(ContainerInterface $runtimeContainer, string $tag, array $serviceIds): \Generator
    {
        foreach ($serviceIds as $serviceId) {
            $service = $runtimeContainer->get($serviceId);
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
