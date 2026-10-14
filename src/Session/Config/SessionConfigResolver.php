<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

/**
 * Resolves layered session configuration definitions into one runtime configuration
 */
final readonly class SessionConfigResolver
{
    /**
     * Applies definitions in registration order to the canonical framework defaults.
     */
    public function resolve(SessionConfigDefinition ...$definitions): SessionConfig
    {
        $config = SessionConfigDefinition::resolvedDefaults();

        foreach ($definitions as $definition) {
            $config = $definition->applyTo($config);
        }

        return $config;
    }
}
