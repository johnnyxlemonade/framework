<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Yaml;

use Lemonade\Framework\Support\Env;
use LogicException;

final class YamlEnvValueResolver
{
    public function resolve(mixed $value, string $path = 'config'): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isEnvDirective($value)) {
            return $this->resolveEnvDirective($value, $path);
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            $resolved[$key] = $this->resolve($item, $path . '.' . (string) $key);
        }

        return $resolved;
    }

    /**
     * @param array<mixed> $value
     */
    private function isEnvDirective(array $value): bool
    {
        return !array_is_list($value) && array_key_exists('$env', $value);
    }

    /**
     * @param array<mixed> $directive
     */
    private function resolveEnvDirective(array $directive, string $path): mixed
    {
        $envKey = $directive['$env'] ?? null;
        if (!is_string($envKey) || trim($envKey) === '') {
            throw new LogicException(sprintf(
                'YAML env directive at "%s" must define a non-empty "$env" key.',
                $path,
            ));
        }

        $type = $directive['type'] ?? 'string';
        if (!is_string($type) || trim($type) === '') {
            throw new LogicException(sprintf(
                'YAML env directive at "%s" must define string "type".',
                $path,
            ));
        }

        $default = array_key_exists('default', $directive)
            ? $this->resolve($directive['default'], $path . '.default')
            : null;

        return match (trim($type)) {
            'string' => $this->resolveString($envKey, $default),
            'int' => Env::int($envKey, $this->normalizeIntDefault($default ?? 0, $path)),
            'float' => Env::float($envKey, $this->normalizeFloatDefault($default ?? 0.0, $path)),
            'bool' => Env::bool($envKey, $this->normalizeBoolDefault($default ?? false, $path)),
            'list' => Env::list($envKey, $this->normalizeListDefault($default ?? [], $path)),
            default => throw new LogicException(sprintf(
                'YAML env directive at "%s" uses unsupported type "%s".',
                $path,
                $type,
            )),
        };
    }

    private function resolveString(string $envKey, mixed $default): ?string
    {
        if ($default === null) {
            return Env::string($envKey, null);
        }

        if (!is_scalar($default)) {
            return null;
        }

        return Env::string($envKey, (string) $default);
    }

    private function normalizeIntDefault(mixed $default, string $path): int
    {
        if (is_int($default)) {
            return $default;
        }

        if (is_float($default) || (is_string($default) && is_numeric($default))) {
            return (int) $default;
        }

        throw new LogicException(sprintf(
            'YAML env directive at "%s" requires int-compatible default.',
            $path,
        ));
    }

    private function normalizeFloatDefault(mixed $default, string $path): float
    {
        if (is_float($default) || is_int($default)) {
            return (float) $default;
        }

        if (is_string($default) && is_numeric($default)) {
            return (float) $default;
        }

        throw new LogicException(sprintf(
            'YAML env directive at "%s" requires float-compatible default.',
            $path,
        ));
    }

    private function normalizeBoolDefault(mixed $default, string $path): bool
    {
        if (is_bool($default)) {
            return $default;
        }

        if (is_int($default)) {
            return $default === 1;
        }

        if (is_string($default)) {
            $value = filter_var($default, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                return $value;
            }
        }

        throw new LogicException(sprintf(
            'YAML env directive at "%s" requires bool-compatible default.',
            $path,
        ));
    }

    /** @return list<string> */
    private function normalizeListDefault(mixed $default, string $path): array
    {
        if (!is_array($default)) {
            throw new LogicException(sprintf(
                'YAML env directive at "%s" requires list default.',
                $path,
            ));
        }

        $normalized = [];

        foreach ($default as $item) {
            if (!is_scalar($item)) {
                throw new LogicException(sprintf(
                    'YAML env directive at "%s" requires scalar list items.',
                    $path,
                ));
            }

            $normalized[] = (string) $item;
        }

        return $normalized;
    }
}
