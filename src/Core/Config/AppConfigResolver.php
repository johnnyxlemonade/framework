<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

final class AppConfigResolver
{
    public function resolve(AppConfigDefinition ...$definitions): AppConfig
    {
        $timezone = null;
        $baseUrl = null;
        $basePath = '';
        $env = 'production';
        $debug = false;
        $appPath = '';
        $configPath = '';
        $storagePath = '';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('timezone', $data)) {
                $timezone = $this->normalizeNullableString($data['timezone']);
            }

            if (array_key_exists('base_url', $data)) {
                $baseUrl = $this->normalizeNullableString($data['base_url']);
            }

            if (array_key_exists('base_path', $data)) {
                $basePath = $this->normalizeString($data['base_path'], $basePath);
            }

            if (array_key_exists('env', $data)) {
                $env = $this->normalizeString($data['env'], $env);
            }

            if (array_key_exists('debug', $data)) {
                $debug = $this->toBool($data['debug'], $debug);
            }

            if (array_key_exists('app_path', $data)) {
                $appPath = $this->normalizeString($data['app_path'], $appPath);
            }

            if (array_key_exists('config_path', $data)) {
                $configPath = $this->normalizeString($data['config_path'], $configPath);
            }

            if (array_key_exists('storage_path', $data)) {
                $storagePath = $this->normalizeString($data['storage_path'], $storagePath);
            }
        }

        return new AppConfig(
            timezone: $timezone,
            baseUrl: $baseUrl,
            basePath: $basePath,
            env: $env,
            debug: $debug,
            appPath: $appPath,
            configPath: $configPath,
            storagePath: $storagePath,
        );
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeString(mixed $value, string $default): string
    {
        $normalized = $this->normalizeNullableString($value);

        return $normalized ?? $default;
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
}
