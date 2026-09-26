<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Lemonade\Framework\Container\Exception\AliasCycleException;

final readonly class CompiledContainerPlan
{
    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, list<string>> $tags
     * @param array<string, string> $aliases
     */
    public function __construct(
        private array $definitions,
        private array $tags,
        array $aliases = [],
    ) {
        $this->aliases = $this->canonicalizeAliases($aliases);
    }

    /** @var array<string, string> */
    private array $aliases;

    public function definition(string $id): ?ServiceDefinition
    {
        return $this->definitions[$this->canonicalId($id)] ?? null;
    }

    public function hasDefinition(string $id): bool
    {
        return isset($this->definitions[$this->canonicalId($id)]);
    }

    public function hasAlias(string $id): bool
    {
        return isset($this->aliases[$id]);
    }

    public function canonicalId(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }

    /** @return list<ServiceDecorator> */
    public function decorators(string $id): array
    {
        $definition = $this->definition($id);
        $decorators = $definition === null ? [] : $definition->decorators;

        usort($decorators, static function (ServiceDecorator $left, ServiceDecorator $right): int {
            $priority = $right->priority <=> $left->priority;

            return $priority !== 0 ? $priority : $left->order <=> $right->order;
        });

        return $decorators;
    }

    /** @return list<string> */
    public function taggedServiceIds(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * @param array<string, string> $aliases
     * @return array<string, string>
     */
    private function canonicalizeAliases(array $aliases): array
    {
        /** @var array<string, string> $canonical */
        $canonical = [];
        /** @var array<string, 'visiting'|'visited'> $states */
        $states = [];
        /** @var list<string> $stack */
        $stack = [];

        foreach (array_keys($aliases) as $alias) {
            $this->canonicalizeAlias($alias, $aliases, $canonical, $states, $stack);
        }

        return $canonical;
    }

    /**
     * @param array<string, string> $aliases
     * @param array<string, string> $canonical
     * @param array<string, 'visiting'|'visited'> $states
     * @param list<string> $stack
     */
    private function canonicalizeAlias(
        string $alias,
        array $aliases,
        array &$canonical,
        array &$states,
        array &$stack,
    ): string {
        if (($states[$alias] ?? null) === 'visiting') {
            $start = array_search($alias, $stack, true);
            $cycle = [...array_slice($stack, $start === false ? 0 : $start), $alias];

            throw new AliasCycleException(sprintf(
                'Service alias cycle detected: %s.',
                implode(' -> ', $cycle),
            ));
        }

        if (($states[$alias] ?? null) === 'visited') {
            return $canonical[$alias];
        }

        $states[$alias] = 'visiting';
        $stack[] = $alias;
        $target = $aliases[$alias];
        $canonical[$alias] = isset($aliases[$target])
            ? $this->canonicalizeAlias($target, $aliases, $canonical, $states, $stack)
            : $target;
        array_pop($stack);
        $states[$alias] = 'visited';

        return $canonical[$alias];
    }
}
