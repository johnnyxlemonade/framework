<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Defines host application service-provider configuration values.
 */
final class ProvidersConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty host provider definition.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the host provider configuration module key.
     */
    public static function moduleKey(): string
    {
        return 'providers';
    }

    /**
     * Appends one host application service-provider class.
     */
    public function provider(string $provider): self
    {
        $this->data[] = $provider;

        return $this;
    }

    /** @param list<string> $providers */
    public function providers(array $providers): self
    {
        $this->data = array_values($providers);

        return $this;
    }
}
