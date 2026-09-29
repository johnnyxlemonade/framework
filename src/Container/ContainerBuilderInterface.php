<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

interface ContainerBuilderInterface extends ContainerInterface
{
    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function set(string $id, callable|object|string $concrete): void;

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function singleton(string $id, callable|object|string $concrete): void;

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function scoped(string $id, callable|object|string $concrete): void;

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|non-empty-string $concrete
     */
    public function transient(string $id, callable|object|string $concrete): void;

    /** @param class-string|non-empty-string $id */
    public function instance(string $id, object $instance): void;

    /**
     * @param class-string|non-empty-string $serviceId
     * @param non-empty-string $tag
     */
    public function tag(string $serviceId, string $tag): void;

    /**
     * Registers a singleton service and declares one or more collection capability tags.
     *
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|class-string $concrete
     * @param non-empty-string ...$tags
     */
    public function singletonTagged(string $id, callable|object|string $concrete, string ...$tags): void;

    /**
     * @param non-empty-string $alias
     * @param non-empty-string $target
     */
    public function alias(string $alias, string $target): void;

    /**
     * @param class-string|non-empty-string $serviceId
     * @param class-string<ServiceDecoratorInterface>|callable(ContainerInterface, mixed):mixed $decorator
     */
    public function decorate(string $serviceId, string|callable $decorator, int $priority = 0): void;

    /** @param class-string|string $consumer */
    public function when(string $consumer): ContextualBindingBuilder;

    public function compile(): CompiledContainerPlan;

    /** Finalizes the registration plan and prohibits all further definition changes. */
    public function freeze(): CompiledContainerPlan;

    public function isFrozen(): bool;

    /** Returns true only when the id is explicitly registered in the builder. */
    public function isBound(string $id): bool;

}
