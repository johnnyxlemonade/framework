<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

/**
 * Resolves layered logging definitions into the framework's minimal built-in logging policy.
 */
final class LoggingConfigResolver
{
    /**
     * Resolves layered logging definitions while retaining only canonical policy keys.
     */
    public function resolve(LoggingConfigDefinition ...$definitions): LoggingConfig
    {
        $retentionDays = 7;
        $requestEnabled = false;
        $requestMinStatus = 0;
        $benchmarkEnabled = false;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $request = $this->assoc($data['request'] ?? null);
            $benchmark = $this->assoc($data['benchmark'] ?? null);

            if (array_key_exists('retention_days', $data)) {
                $retentionDays = max(1, $this->intOr($data['retention_days'], $retentionDays));
            }

            if (array_key_exists('enabled', $request)) {
                $requestEnabled = $this->toBool($request['enabled'], $requestEnabled);
            }

            if (array_key_exists('min_status', $request)) {
                $requestMinStatus = max(0, $this->intOr($request['min_status'], $requestMinStatus));
            }

            if (array_key_exists('enabled', $benchmark)) {
                $benchmarkEnabled = $this->toBool($benchmark['enabled'], $benchmarkEnabled);
            }
        }

        return new LoggingConfig(
            retentionDays: $retentionDays,
            requestEnabled: $requestEnabled,
            requestMinStatus: $requestMinStatus,
            benchmarkEnabled: $benchmarkEnabled,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assoc(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
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

    private function intOr(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
