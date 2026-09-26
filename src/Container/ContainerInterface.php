<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|class-string $concrete
     */
    public function set(string $id, callable|object|string $concrete): void;

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|class-string $concrete
     */
    public function singleton(string $id, callable|object|string $concrete): void;

    /**
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|class-string $concrete
     */
    public function scoped(string $id, callable|object|string $concrete): void;

    /**
     * Registers a singleton service and declares one or more collection capability tags.
     *
     * @param class-string|non-empty-string $id
     * @param callable(ContainerInterface):mixed|object|class-string $concrete
     * @param non-empty-string ...$tags
     */
    public function singletonTagged(string $id, callable|object|string $concrete, string ...$tags): void;

    /**
     * Declares an explicit bound service as a member of a collection capability.
     *
     * @param class-string|non-empty-string $serviceId
     * @param non-empty-string $tag
     */
    public function tag(string $serviceId, string $tag): void;

    /**
     * Resolves tagged services in their declaration order.
     *
     * @param non-empty-string $tag
     * @return iterable<string, object>
     */
    public function tagged(string $tag): iterable;

    public function setDiagnosticLogger(?LoggerInterface $logger): void;

    /**
     * @param class-string|string $id
     */
    public function has(string $id): bool;

    /**
     * Returns true only when the id is explicitly known by the container.
     *
     * @param class-string|string $id
     */
    public function isBound(string $id): bool;

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed;
}
