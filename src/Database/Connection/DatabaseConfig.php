<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

use Lemonade\Framework\Database\Exception\DatabaseException;

/**
 * Carries normalized connection settings independently of configuration source format.
 */
final readonly class DatabaseConfig
{
    /**
     * Captures all connection options after their caller-selected defaults have been applied.
     *
     * @param array<int|string, mixed> $options
     */
    public function __construct(
        private Driver $driver,
        private string $host,
        private int $port,
        private string $database,
        private string $username,
        private string $password,
        private string $charset,
        private string $collation = '',
        private string $prefix = '',
        private bool $strict = true,
        private bool $persistent = false,
        private ?DatabaseDialect $dialect = null,
        private ?string $dsn = null,
        private array $options = [],
    ) {
    }

    /**
     * Normalizes an untrusted connection mapping into a validated immutable configuration.
     *
     * @param array<string, mixed> $config
     * @throws DatabaseException When the driver or dialect cannot be resolved.
     */
    public static function fromArray(array $config): self
    {
        $driver = self::toString($config['driver'] ?? 'mysql', 'mysql');
        $resolvedDriver = self::resolveDriver($driver);
        $dialect = self::resolveDialect($config['dialect'] ?? null);

        return new self(
            driver: $resolvedDriver,
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
            dialect: $dialect,
            dsn: self::normalizeNullableString($config['dsn'] ?? null),
            options: is_array($config['options'] ?? null) ? $config['options'] : [],
        );
    }

    /**
     * Returns the selected connection driver family.
     */
    public function driver(): Driver
    {
        return $this->driver;
    }

    /**
     * Returns the configured network host for network-capable drivers.
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * Returns the configured network port for network-capable drivers.
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * Returns the selected database or catalog name.
     */
    public function database(): string
    {
        return $this->database;
    }

    /**
     * Returns the configured database login name.
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Returns the configured database login secret.
     */
    public function password(): string
    {
        return $this->password;
    }

    /**
     * Returns the connection character set requested from the driver.
     */
    public function charset(): string
    {
        return $this->charset;
    }

    /**
     * Returns the optional collation requested after connection setup.
     */
    public function collation(): string
    {
        return $this->collation;
    }

    /**
     * Returns the table-name prefix applied by driver-level identifier protection.
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Indicates whether the connection should request strict SQL behavior where supported.
     */
    public function strict(): bool
    {
        return $this->strict;
    }

    /**
     * Indicates whether the driver may reuse a persistent physical connection.
     */
    public function persistent(): bool
    {
        return $this->persistent;
    }

    /**
     * Returns an explicitly configured dialect or the dialect implied by a concrete driver.
     */
    public function dialect(): ?DatabaseDialect
    {
        if ($this->dialect instanceof DatabaseDialect) {
            return $this->dialect;
        }

        return match ($this->driver) {
            Driver::Mysql => DatabaseDialect::Mysql,
            Driver::Odbc => DatabaseDialect::Odbc,
            Driver::Pdo => null,
        };
    }

    /**
     * Returns an explicit DSN when it overrides host-based DSN construction.
     */
    public function dsn(): ?string
    {
        return $this->dsn;
    }

    /**
     * Returns driver-specific connection options retained without framework interpretation.
     *
     * @return array<int|string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Converts a scalar optional setting to a trimmed value and treats blank or structured input as absent.
     */
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

    /**
     * Converts a scalar setting to text or preserves the supplied fallback for structured input.
     */
    private static function toString(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Accepts numeric configuration values while retaining the fallback for non-numeric input.
     */
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

    /**
     * Parses supported boolean configuration syntax without coercing arbitrary values.
     */
    private static function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        return $default;
    }

    /**
     * Resolves an optional dialect name and rejects names unsupported by the framework.
     *
     * @throws DatabaseException When a non-empty dialect name is unsupported.
     */
    private static function resolveDialect(mixed $value): ?DatabaseDialect
    {
        $rawDialect = self::normalizeNullableString($value);

        if ($rawDialect === null) {
            return null;
        }

        $dialect = DatabaseDialect::tryFrom(strtolower($rawDialect));

        if (!$dialect instanceof DatabaseDialect) {
            throw DatabaseException::invalidConfiguration(
                sprintf('Unsupported database dialect: %s', $rawDialect),
            );
        }

        return $dialect;
    }

    /**
     * Resolves the required driver name and rejects unsupported driver families.
     *
     * @throws DatabaseException When the configured driver name is unsupported.
     */
    private static function resolveDriver(string $driver): Driver
    {
        $normalizedDriver = self::normalizeNullableString($driver);
        $resolvedDriver = is_string($normalizedDriver) ? Driver::tryFrom(strtolower($normalizedDriver)) : null;

        if (!$resolvedDriver instanceof Driver) {
            throw DatabaseException::invalidConfiguration(
                sprintf('Unsupported database driver: %s', $driver),
            );
        }

        return $resolvedDriver;
    }
}
