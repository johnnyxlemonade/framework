<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

/** @internal Immutable, globally validated provider bootstrap plan. */
final readonly class ProviderLifecyclePlan
{
    /** @var list<object> */
    private array $providers;

    /** @param list<object> $providers */
    public function __construct(array $providers)
    {
        $this->providers = (new ProviderDependencyResolver())->sort($providers);
    }

    /** @return list<object> */
    public function providers(): array
    {
        return $this->providers;
    }
}
