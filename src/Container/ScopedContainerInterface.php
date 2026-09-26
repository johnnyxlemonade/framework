<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

interface ScopedContainerInterface extends ContainerInterface
{
    public function kind(): ScopeKind;

    public function close(): void;
}
