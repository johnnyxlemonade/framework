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
