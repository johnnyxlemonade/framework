<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;

/**
 * Carries one loaded definition together with its environment and source dependencies.
 */
final readonly class LoadedConfigFile
{
    /**
     * @param list<string> $envKeys
     * @param list<string> $sourceFiles
     */
    public function __construct(
        private ConfigDefinitionInterface $definition,
        private array $envKeys = [],
        private array $sourceFiles = [],
    ) {
    }

    /**
     * Returns the typed configuration definition loaded from the source file.
     */
    public function definition(): ConfigDefinitionInterface
    {
        return $this->definition;
    }

    /**
     * @return list<string>
     */
    public function envKeys(): array
    {
        return $this->envKeys;
    }

    /**
     * @return list<string>
     */
    public function sourceFiles(): array
    {
        return $this->sourceFiles;
    }
}
