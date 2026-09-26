<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

interface ContainerBuilderInterface
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
    public function transient(string $id, callable|object|string $concrete): void;

    /** @param class-string|non-empty-string $id */
    public function instance(string $id, object $instance): void;

    /**
     * @param class-string|non-empty-string $serviceId
     * @param non-empty-string $tag
     */
    public function tag(string $serviceId, string $tag): void;

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

    public function compile(): CompiledContainerPlan;
}
