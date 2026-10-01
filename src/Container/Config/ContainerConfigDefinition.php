<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class ContainerConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty definition that application configuration may populate.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the configuration module name used by YAML definitions.
     */
    public static function moduleKey(): string
    {
        return 'container';
    }

    /**
     * Selects permissive or strict handling for unbound concrete service IDs.
     */
    public function autowire(string $mode): self
    {
        return $this->set('autowire', $mode);
    }
}
