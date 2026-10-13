<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Definition;

/**
 * Defines the typed representation of one named configuration module.
 */
interface ConfigDefinitionInterface
{
    /**
     * Returns the stable module key used to group this definition.
     */
    public static function moduleKey(): string;

    /**
     * @return array<mixed>
     */
    public function toArray(): array;
}
