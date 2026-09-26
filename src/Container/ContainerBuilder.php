<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Closure;
use Lemonade\Framework\Container\Definition\ClassTarget;
use Lemonade\Framework\Container\Definition\DefinitionTarget;
use Lemonade\Framework\Container\Definition\FactoryTarget;
use Lemonade\Framework\Container\Definition\InstanceTarget;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\DecorationTargetNotFoundException;
use Lemonade\Framework\Container\Exception\DuplicateServiceAliasException;
use Lemonade\Framework\Container\Exception\InvalidServiceAliasException;
use Lemonade\Framework\Container\Exception\InvalidServiceDecoratorException;
use ReflectionClass;

final class ContainerBuilder implements ContainerBuilderInterface
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $aliases = [];

    private int $decoratorOrder = 0;

    /**
     * @param class-string|string $id
     * @param callable(ContainerInterface):mixed|object|string $concrete
     */
    public function set(string $id, callable|object|string $concrete): void
    {
        $this->define($id, $concrete, ServiceLifetime::Transient);
    }

    /**
     * @param class-string|string $id
     * @param callable(ContainerInterface):mixed|object|string $concrete
     */
    public function transient(string $id, callable|object|string $concrete): void
    {
        $this->set($id, $concrete);
    }

    /**
     * @param class-string|string $id
     * @param callable(ContainerInterface):mixed|object|string $concrete
     */
    public function singleton(string $id, callable|object|string $concrete): void
    {
        $this->define($id, $concrete, ServiceLifetime::Singleton);
    }

    /** @param class-string|string $id */
    public function instance(string $id, object $instance): void
    {
        if (isset($this->aliases[$id])) {
            throw new DuplicateServiceAliasException(sprintf(
                'Service definition "%s" conflicts with an existing service alias.',
                $id,
            ));
        }

        $this->definitions[$id] = new ServiceDefinition(
            id: $id,
            lifetime: ServiceLifetime::Singleton,
            target: new InstanceTarget($instance),
            tags: $this->definitions[$id]->tags ?? [],
            decorators: $this->definitions[$id]->decorators ?? [],
        );
    }

    public function hasDefinition(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    public function tag(string $serviceId, string $tag): void
    {
        $definition = $this->definitions[$serviceId] ?? null;
        if ($definition === null) {
            throw new ContainerException(sprintf(
                'Tagged service "%s" must be explicitly bound before it can be tagged.',
                $serviceId,
            ));
        }

        $tag = $this->normalizeTag($tag);
        if (in_array($tag, $definition->tags, true)) {
            throw new ContainerException(sprintf(
                'Service "%s" is already tagged with "%s".',
                $serviceId,
                $tag,
            ));
        }

        $this->definitions[$serviceId] = $definition->withTag($tag);
    }

    public function alias(string $alias, string $target): void
    {
        $alias = $this->normalizeAliasPart($alias, 'Alias');
        $target = $this->normalizeAliasPart($target, 'Alias target');

        if ($alias === $target) {
            throw new InvalidServiceAliasException(sprintf(
                'Service alias "%s" must not target itself.',
                $alias,
            ));
        }

        if (isset($this->definitions[$alias])) {
            throw new DuplicateServiceAliasException(sprintf(
                'Service alias "%s" conflicts with an existing service definition.',
                $alias,
            ));
        }

        if (isset($this->aliases[$alias])) {
            throw new DuplicateServiceAliasException(sprintf(
                'Service alias "%s" is already registered.',
                $alias,
            ));
        }

        $this->aliases[$alias] = $target;
    }

    public function decorate(string $serviceId, string|callable $decorator, int $priority = 0): void
    {
        $canonicalId = $this->canonicalDefinitionId($serviceId);
        $definition = $this->definitions[$canonicalId] ?? null;
        if ($definition === null) {
            throw new DecorationTargetNotFoundException(sprintf(
                'Service "%s" resolves to "%s", but the decoration target is not defined.',
                $serviceId,
                $canonicalId,
            ));
        }

        $this->definitions[$canonicalId] = $definition->withDecorator(new ServiceDecorator(
            decorator: $this->decoratorTarget($decorator),
            priority: $priority,
            order: $this->decoratorOrder++,
        ));
    }

    public function compile(): CompiledContainerPlan
    {
        $tags = [];

        foreach ($this->definitions as $definition) {
            foreach ($definition->tags as $tag) {
                $tags[$tag][] = $definition->id;
            }
        }

        return new CompiledContainerPlan($this->definitions, $tags, $this->aliases);
    }

    /**
     * @param class-string|string $id
     * @param callable(ContainerInterface):mixed|object|string $concrete
     */
    private function define(string $id, callable|object|string $concrete, ServiceLifetime $lifetime): void
    {
        if (isset($this->aliases[$id])) {
            throw new DuplicateServiceAliasException(sprintf(
                'Service definition "%s" conflicts with an existing service alias.',
                $id,
            ));
        }

        $target = $this->target($concrete);

        $this->definitions[$id] = new ServiceDefinition(
            id: $id,
            lifetime: $target instanceof InstanceTarget ? ServiceLifetime::Singleton : $lifetime,
            target: $target,
            tags: $this->definitions[$id]->tags ?? [],
            decorators: $this->definitions[$id]->decorators ?? [],
        );
    }

    /** @param callable(ContainerInterface):mixed|object|string $concrete */
    private function target(callable|object|string $concrete): DefinitionTarget
    {
        if (is_string($concrete)) {
            return new ClassTarget($concrete);
        }

        if (is_callable($concrete)) {
            return new FactoryTarget(Closure::fromCallable($concrete));
        }

        return new InstanceTarget($concrete);
    }

    private function normalizeTag(string $tag): string
    {
        $normalizedTag = trim($tag);
        if ($normalizedTag === '') {
            throw new ContainerException('Service tag must not be empty.');
        }

        return $normalizedTag;
    }

    private function normalizeAliasPart(string $value, string $label): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new InvalidServiceAliasException(sprintf('%s must not be empty.', $label));
        }

        return $normalized;
    }

    private function canonicalDefinitionId(string $serviceId): string
    {
        $id = $serviceId;
        /** @var array<string, true> $visited */
        $visited = [];

        while (isset($this->aliases[$id])) {
            if (isset($visited[$id])) {
                throw new InvalidServiceDecoratorException(sprintf(
                    'Cannot decorate "%s" because its service alias chain contains a cycle.',
                    $serviceId,
                ));
            }

            $visited[$id] = true;
            $id = $this->aliases[$id];
        }

        return $id;
    }

    /**
     * @param class-string<ServiceDecoratorInterface>|callable(ContainerInterface, mixed):mixed $decorator
     * @return \Closure(ContainerInterface, mixed):mixed|class-string<ServiceDecoratorInterface>
     */
    private function decoratorTarget(string|callable $decorator): Closure|string
    {
        if (!is_string($decorator)) {
            return Closure::fromCallable($decorator);
        }

        if (!class_exists($decorator)) {
            throw new InvalidServiceDecoratorException(sprintf(
                'Service decorator class "%s" does not exist.',
                $decorator,
            ));
        }

        $reflection = new ReflectionClass($decorator);
        if (!$reflection->isInstantiable()) {
            throw new InvalidServiceDecoratorException(sprintf(
                'Service decorator class "%s" is not instantiable.',
                $decorator,
            ));
        }

        if (!is_subclass_of($decorator, ServiceDecoratorInterface::class)) {
            throw new InvalidServiceDecoratorException(sprintf(
                'Service decorator class "%s" must implement %s.',
                $decorator,
                ServiceDecoratorInterface::class,
            ));
        }

        /** @var class-string<ServiceDecoratorInterface> $decorator */
        return $decorator;
    }
}
