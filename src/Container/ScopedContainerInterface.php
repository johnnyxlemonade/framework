<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

interface ScopedContainerInterface extends ContainerInterface
{
    public function kind(): ScopeKind;

    /** @param class-string|non-empty-string $id */
    public function hasScopedBinding(string $id): bool;

    /** @param class-string|non-empty-string $id */
    public function bindScopedInstance(string $id, object $instance): void;

    public function close(): void;
}
