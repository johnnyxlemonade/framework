<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Config;

use Lemonade\Framework\Database\Connection\DatabaseConfig;

final class DatabaseConfigResolver
{
    public function resolve(DatabaseConfigDefinition ...$definitions): DatabaseRuntimeConfig
    {
        $defaultConnection = null;
        $connections = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('default', $data)) {
                $defaultConnection = $this->nullableString($data['default']);
            }

            if (array_key_exists('connections', $data) && is_array($data['connections'])) {
                foreach ($data['connections'] as $name => $connectionConfig) {
                    if (!is_string($name) || trim($name) === '' || !is_array($connectionConfig)) {
                        continue;
                    }

                    $connections[trim($name)] = DatabaseConfig::fromArray($this->normalizeAssoc($connectionConfig));
                }
            }
        }

        return new DatabaseRuntimeConfig($defaultConnection, $connections);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeAssoc(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
