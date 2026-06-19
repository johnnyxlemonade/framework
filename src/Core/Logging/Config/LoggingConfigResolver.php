<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

final class LoggingConfigResolver
{
    public function resolve(LoggingConfigDefinition ...$definitions): LoggingConfig
    {
        $app = ['enabled' => true, 'path' => 'app.log', 'level' => 'info', 'days' => 7];
        $error = ['enabled' => true, 'path' => 'error.log', 'level' => 'error', 'days' => 7, 'not_found' => false];
        $request = ['enabled' => false, 'path' => 'request.log', 'level' => 'info', 'days' => 7, 'min_status' => 0];
        $benchmark = ['enabled' => false, 'path' => 'benchmark.log', 'level' => 'debug', 'days' => 7];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $app = $this->mergeChannel($app, $this->assoc($data['app'] ?? null));
            $error = $this->mergeChannel($error, $this->assoc($data['error'] ?? null), ['not_found']);
            $request = $this->mergeChannel($request, $this->assoc($data['request'] ?? null), ['min_status']);
            $benchmark = $this->mergeChannel($benchmark, $this->assoc($data['benchmark'] ?? null));
        }

        return new LoggingConfig(
            app: $this->channelConfig($app),
            error: $this->channelConfig($error),
            request: $this->channelConfig($request),
            benchmark: $this->channelConfig($benchmark),
            requestMinStatus: max(0, $this->intOr($request['min_status'] ?? 0, 0)),
            errorLogNotFound: $this->toBool($error['not_found'] ?? false, false),
        );
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $override
     * @param list<string> $extraKeys
     * @return array<string, mixed>
     */
    private function mergeChannel(array $defaults, array $override, array $extraKeys = []): array
    {
        if (array_key_exists('enabled', $override)) {
            $defaults['enabled'] = $this->toBool($override['enabled'], $this->toBool($defaults['enabled'] ?? false, false));
        }

        if (array_key_exists('path', $override)) {
            $defaults['path'] = $this->stringOr($override['path'], $this->stringOr($defaults['path'] ?? '', ''));
        }

        if (array_key_exists('level', $override)) {
            $defaults['level'] = $this->stringOr($override['level'], $this->stringOr($defaults['level'] ?? 'info', 'info'));
        }

        if (array_key_exists('days', $override)) {
            $defaults['days'] = max(1, $this->intOr($override['days'], $this->intOr($defaults['days'] ?? 7, 7)));
        }

        foreach ($extraKeys as $key) {
            if (!array_key_exists($key, $override)) {
                continue;
            }

            if ($key === 'not_found') {
                $defaults[$key] = $this->toBool($override[$key], (bool) ($defaults[$key] ?? false));
            }

            if ($key === 'min_status') {
                $defaults[$key] = max(0, $this->intOr($override[$key], $this->intOr($defaults[$key] ?? 0, 0)));
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function channelConfig(array $data): LoggingChannelConfig
    {
        return new LoggingChannelConfig(
            enabled: $this->toBool($data['enabled'] ?? false, false),
            path: $this->stringOr($data['path'] ?? '', ''),
            level: $this->stringOr($data['level'] ?? 'info', 'info'),
            days: max(1, $this->intOr($data['days'] ?? 7, 7)),
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

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
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
