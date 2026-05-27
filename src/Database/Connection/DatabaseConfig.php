<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

final class DatabaseConfig
{
    /**
     * @param array<int|string, mixed> $options
     */
    public function __construct(
        private readonly Driver $driver,
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset,
        private readonly string $collation = '',
        private readonly string $prefix = '',
        private readonly bool $strict = true,
        private readonly bool $persistent = false,
        private readonly ?string $dsn = null,
        private readonly array $options = [],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $driver = self::toString($config['driver'] ?? 'mysql', 'mysql');

        return new self(
            driver: Driver::from($driver),
            host: self::toString($config['host'] ?? '127.0.0.1', '127.0.0.1'),
            port: self::toInt($config['port'] ?? 3306, 3306),
            database: self::toString($config['database'] ?? '', ''),
            username: self::toString($config['username'] ?? '', ''),
            password: self::toString($config['password'] ?? '', ''),
            charset: self::toString($config['charset'] ?? 'utf8mb4', 'utf8mb4'),
            collation: self::toString($config['collation'] ?? '', ''),
            prefix: self::toString($config['prefix'] ?? '', ''),
            strict: self::toBool($config['strict'] ?? true, true),
            persistent: self::toBool($config['persistent'] ?? false, false),
            dsn: self::normalizeNullableString($config['dsn'] ?? null),
            options: is_array($config['options'] ?? null) ? $config['options'] : [],
        );
    }

    public function driver(): Driver
    {
        return $this->driver;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function database(): string
    {
        return $this->database;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function charset(): string
    {
        return $this->charset;
    }

    public function collation(): string
    {
        return $this->collation;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function strict(): bool
    {
        return $this->strict;
    }

    public function persistent(): bool
    {
        return $this->persistent;
    }

    public function dsn(): ?string
    {
        return $this->dsn;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function toString(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }

    private static function toInt(mixed $value, int $default): int
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

    private static function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        return $default;
    }
}
