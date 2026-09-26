<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Closure;
use Lemonade\Framework\Container\Definition\ClassTarget;
use Lemonade\Framework\Container\Definition\DefinitionTarget;
use Lemonade\Framework\Container\Definition\FactoryTarget;
use Lemonade\Framework\Container\Definition\InstanceTarget;
use Lemonade\Framework\Container\Exception\ContainerException;

final class ContainerBuilder
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

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
        $this->definitions[$id] = new ServiceDefinition(
            id: $id,
            lifetime: ServiceLifetime::Singleton,
            target: new InstanceTarget($instance),
            tags: $this->definitions[$id]->tags ?? [],
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

    public function compile(): CompiledContainerPlan
    {
        $tags = [];

        foreach ($this->definitions as $definition) {
            foreach ($definition->tags as $tag) {
                $tags[$tag][] = $definition->id;
            }
        }

        return new CompiledContainerPlan($this->definitions, $tags);
    }

    /**
     * @param class-string|string $id
     * @param callable(ContainerInterface):mixed|object|string $concrete
     */
    private function define(string $id, callable|object|string $concrete, ServiceLifetime $lifetime): void
    {
        $target = $this->target($concrete);

        $this->definitions[$id] = new ServiceDefinition(
            id: $id,
            lifetime: $target instanceof InstanceTarget ? ServiceLifetime::Singleton : $lifetime,
            target: $target,
            tags: $this->definitions[$id]->tags ?? [],
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
}
