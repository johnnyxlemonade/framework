<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

/**
 * Resolves ordered application configuration definitions into immutable runtime configuration.
 */
final class AppConfigResolver
{
    /** @var list<string> */
    private const array NULLABLE_STRING_FIELDS = ['timezone', 'base_url', 'secret'];

    /** @var list<string> */
    private const array STRING_FIELDS = ['base_path', 'public_path', 'env', 'app_path', 'config_path', 'storage_path'];

    /**
     * Merges definitions in order, allowing later definitions to override earlier values.
     */
    public function resolve(AppConfigDefinition ...$definitions): AppConfig
    {
        $values = [
            'timezone' => null,
            'base_url' => null,
            'secret' => null,
            'base_path' => '',
            'public_path' => '',
            'env' => 'production',
            'debug' => false,
            'app_path' => '',
            'config_path' => '',
            'storage_path' => '',
        ];

        foreach ($definitions as $definition) {
            $values = $this->merge($values, $definition->toArray());
        }

        return new AppConfig(
            timezone: $values['timezone'],
            baseUrl: $values['base_url'],
            basePath: $values['base_path'],
            publicPath: $values['public_path'],
            env: $values['env'],
            debug: $values['debug'],
            appPath: $values['app_path'],
            configPath: $values['config_path'],
            storagePath: $values['storage_path'],
            secret: $values['secret'],
        );
    }

    /**
     * Applies supported values from one definition to the accumulated runtime configuration.
     *
     * @param array{
     *     timezone: string|null,
     *     base_url: string|null,
     *     secret: string|null,
     *     base_path: string,
     *     public_path: string,
     *     env: string,
     *     debug: bool,
     *     app_path: string,
     *     config_path: string,
     *     storage_path: string
     * } $values
     * @param array<mixed> $definition
     * @return array{
     *     timezone: string|null,
     *     base_url: string|null,
     *     secret: string|null,
     *     base_path: string,
     *     public_path: string,
     *     env: string,
     *     debug: bool,
     *     app_path: string,
     *     config_path: string,
     *     storage_path: string
     * }
     */
    private function merge(array $values, array $definition): array
    {
        foreach ($definition as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($key === 'debug') {
                $values['debug'] = $this->toBool($value, $values['debug']);

                continue;
            }

            if (in_array($key, self::NULLABLE_STRING_FIELDS, true)) {
                $values[$key] = $this->normalizeNullableString($value);

                continue;
            }

            if (in_array($key, self::STRING_FIELDS, true)) {
                $values[$key] = $this->normalizeString($value, (string) $values[$key]);
            }
        }

        /** @var array{
         *     timezone: string|null,
         *     base_url: string|null,
         *     secret: string|null,
         *     base_path: string,
         *     public_path: string,
         *     env: string,
         *     debug: bool,
         *     app_path: string,
         *     config_path: string,
         *     storage_path: string
         * } $values
         */
        return $values;
    }

    /**
     * Converts a scalar configuration value into a trimmed nullable string.
     */
    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Converts a configuration value into a string or preserves its fallback.
     */
    private function normalizeString(mixed $value, string $default): string
    {
        $normalized = $this->normalizeNullableString($value);

        return $normalized ?? $default;
    }

    /**
     * Converts a scalar configuration value into a boolean or preserves its fallback.
     */
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
