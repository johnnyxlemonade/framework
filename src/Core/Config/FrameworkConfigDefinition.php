<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Defines framework service-provider configuration values.
 */
final class FrameworkConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty framework provider definition.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the framework configuration module key.
     */
    public static function moduleKey(): string
    {
        return 'framework';
    }

    /**
     * Appends one framework service-provider class.
     */
    public function provider(string $provider): self
    {
        return $this->append('providers', $provider);
    }

    /**
     * @param list<string> $providers
     */
    public function providers(array $providers): self
    {
        return $this->set('providers', array_values($providers));
    }
}
