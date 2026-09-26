<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

interface ScopeFactoryInterface
{
    public function beginScope(ScopeKind $kind): ScopedContainerInterface;
}
