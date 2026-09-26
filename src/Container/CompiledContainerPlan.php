<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

final readonly class CompiledContainerPlan
{
    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, list<string>> $tags
     */
    public function __construct(
        private array $definitions,
        private array $tags,
    ) {}

    public function definition(string $id): ?ServiceDefinition
    {
        return $this->definitions[$id] ?? null;
    }

    public function hasDefinition(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    /** @return list<string> */
    public function taggedServiceIds(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }
}
