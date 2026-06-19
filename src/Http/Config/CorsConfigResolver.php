<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class CorsConfigResolver
{
    public function resolve(CorsConfigDefinition ...$definitions): CorsConfig
    {
        $enabled = false;
        $allowedOrigins = [];
        $allowedMethods = [];
        $allowedHeaders = [];
        $exposedHeaders = [];
        $allowCredentials = false;
        $maxAge = null;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('enabled', $data)) {
                $enabled = $this->toBool($data['enabled'], $enabled);
            }

            if (array_key_exists('allowed_origins', $data)) {
                $allowedOrigins = $this->normalizeStringList($data['allowed_origins']);
            }

            if (array_key_exists('allowed_methods', $data)) {
                $allowedMethods = $this->normalizeStringList($data['allowed_methods']);
            }

            if (array_key_exists('allowed_headers', $data)) {
                $allowedHeaders = $this->normalizeStringList($data['allowed_headers']);
            }

            if (array_key_exists('exposed_headers', $data)) {
                $exposedHeaders = $this->normalizeStringList($data['exposed_headers']);
            }

            if (array_key_exists('allow_credentials', $data)) {
                $allowCredentials = $this->toBool($data['allow_credentials'], $allowCredentials);
            }

            if (array_key_exists('max_age', $data)) {
                $maxAge = $this->normalizeNullableInt($data['max_age']);
            }
        }

        return new CorsConfig(
            enabled: $enabled,
            allowedOrigins: $allowedOrigins,
            allowedMethods: $allowedMethods,
            allowedHeaders: $allowedHeaders,
            exposedHeaders: $exposedHeaders,
            allowCredentials: $allowCredentials,
            maxAge: $maxAge,
        );
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $default;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @return list<non-empty-string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $normalized = trim((string) $item);
            if ($normalized === '' || in_array($normalized, $values, true)) {
                continue;
            }

            $values[] = $normalized;
        }

        return $values;
    }
}
