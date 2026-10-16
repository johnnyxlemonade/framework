<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;
use Lemonade\Framework\Database\Connection\DatabaseConfig;

/**
 * Merges ordered database configuration contributions into runtime connection definitions.
 */
final readonly class DatabaseConfigResolver
{
    /**
     * Resolves definitions in registration order; later explicitly declared connection names replace earlier ones.
     */
    public function resolve(DatabaseConfigDefinition ...$definitions): DatabaseRuntimeConfig
    {
        $defaultConnection = null;
        $connections = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('default', $data)) {
                $defaultConnection = ConfigValueNormalizer::nullableString($data['default']);
            }

            if (array_key_exists('connections', $data) && is_array($data['connections'])) {
                foreach ($data['connections'] as $name => $connectionConfig) {
                    if (!is_string($name) || trim($name) === '' || !is_array($connectionConfig)) {
                        continue;
                    }

                    $connections[trim($name)] = DatabaseConfig::fromArray(
                        ConfigValueNormalizer::assoc($connectionConfig),
                    );
                }
            }
        }

        return new DatabaseRuntimeConfig($defaultConnection, $connections);
    }

}
