<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Definition;

use LogicException;

final class ConfigDefinitionRegistry
{
    /**
     * @var array<string, list<ConfigDefinitionInterface>>
     */
    private array $entries = [];

    public function addDefinition(ConfigDefinitionInterface $definition): void
    {
        $this->entries[$definition::moduleKey()][] = $definition;
    }

    /**
     * @return list<ConfigDefinitionInterface>
     */
    public function entriesFor(string $moduleKey): array
    {
        return $this->entries[$moduleKey] ?? [];
    }

    /**
     * @template T of ConfigDefinitionInterface
     * @param class-string<T> $definitionClass
     * @return list<T>
     */
    public function typedEntriesFor(string $moduleKey, string $definitionClass): array
    {
        $typed = [];

        foreach ($this->entriesFor($moduleKey) as $entry) {
            if (!$entry instanceof $definitionClass) {
                throw new LogicException(sprintf(
                    'Config definition for module "%s" must be instance of %s, %s given.',
                    $moduleKey,
                    $definitionClass,
                    $entry::class,
                ));
            }

            $typed[] = $entry;
        }

        return $typed;
    }
}
