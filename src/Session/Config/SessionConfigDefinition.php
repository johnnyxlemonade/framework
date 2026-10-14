<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds declarative session configuration entries for application config sources
 */
final class SessionConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an empty session configuration contribution
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Creates the framework's canonical default session configuration contribution.
     */
    public static function defaults(): self
    {
        return self::create()
            ->driver('native')
            ->cookie('LEMONADE_SESSION')
            ->lifetime(7200)
            ->nativePath('sessions')
            ->filePath('sessions')
            ->databaseTable('sessions')
            ->redisHost('127.0.0.1')
            ->redisPort(6379)
            ->redisDatabase(0)
            ->redisPassword(null)
            ->redisPrefix('sess:')
            ->redisTimeout(2.5);
    }

    /**
     * Resolves the framework's canonical default contribution into runtime configuration.
     */
    public static function resolvedDefaults(): SessionConfig
    {
        return self::defaults()->toDefaultConfig();
    }

    /**
     * Returns the stable config module key consumed by the session provider
     */
    public static function moduleKey(): string
    {
        return 'session';
    }

    /**
     * Selects the storage driver used for session persistence
     */
    public function driver(string $driver): self
    {
        return $this->set('driver', $driver);
    }

    /**
     * Sets the browser cookie name that carries the session identifier
     */
    public function cookie(string $cookie): self
    {
        return $this->set('cookie', $cookie);
    }

    /**
     * Sets the shared cookie and storage retention period in seconds
     */
    public function lifetime(int $seconds): self
    {
        return $this->set('lifetime', $seconds);
    }

    /**
     * Sets the writable storage path used by native PHP sessions
     */
    public function nativePath(string $path): self
    {
        return $this->set('native.path', $path);
    }

    /**
     * Sets the writable storage path used by file-backed sessions
     */
    public function filePath(string $path): self
    {
        return $this->set('file.path', $path);
    }

    /**
     * Sets the database table used by database-backed sessions
     */
    public function databaseTable(string $table): self
    {
        return $this->set('database.table', $table);
    }

    /**
     * Sets the Redis host used by Redis-backed sessions
     */
    public function redisHost(string $host): self
    {
        return $this->set('redis.host', $host);
    }

    /**
     * Sets the Redis TCP port used by Redis-backed sessions
     */
    public function redisPort(int $port): self
    {
        return $this->set('redis.port', $port);
    }

    /**
     * Sets the Redis logical database used by Redis-backed sessions
     */
    public function redisDatabase(int $database): self
    {
        return $this->set('redis.database', $database);
    }

    /**
     * Sets the optional Redis authentication secret
     */
    public function redisPassword(?string $password): self
    {
        return $this->set('redis.password', $password);
    }

    /**
     * Sets the namespace prefix applied to Redis session keys
     */
    public function redisPrefix(string $prefix): self
    {
        return $this->set('redis.prefix', $prefix);
    }

    /**
     * Sets the connection timeout for Redis-backed sessions in seconds
     */
    public function redisTimeout(float|int $seconds): self
    {
        return $this->set('redis.timeout', $seconds);
    }

    /**
     * Applies this raw configuration contribution to an already resolved session configuration.
     *
     * Missing fields preserve their current values, while an explicitly configured Redis password may clear it.
     */
    public function applyTo(SessionConfig $current): SessionConfig
    {
        $data = $this->toArray();
        $native = is_array($data['native'] ?? null) ? $data['native'] : [];
        $file = is_array($data['file'] ?? null) ? $data['file'] : [];
        $database = is_array($data['database'] ?? null) ? $data['database'] : [];
        $redis = is_array($data['redis'] ?? null) ? $data['redis'] : [];

        $redisPassword = array_key_exists('password', $redis)
            ? $this->nullableString($redis['password'])
            : $current->redis()->password();

        return new SessionConfig(
            driver: strtolower($this->stringOr($data['driver'] ?? null, $current->driver())),
            cookie: $this->stringOr($data['cookie'] ?? null, $current->cookie()),
            lifetime: max(1, $this->intOr($data['lifetime'] ?? null, $current->lifetime())),
            native: new SessionNativeConfig(
                $this->stringOr($native['path'] ?? null, $current->native()->path()),
            ),
            file: new SessionFileConfig(
                $this->stringOr($file['path'] ?? null, $current->file()->path()),
            ),
            database: new SessionDatabaseConfig(
                $this->stringOr($database['table'] ?? null, $current->database()->table()),
            ),
            redis: new SessionRedisConfig(
                host: $this->stringOr($redis['host'] ?? null, $current->redis()->host()),
                port: $this->intOr($redis['port'] ?? null, $current->redis()->port()),
                database: $this->intOr($redis['database'] ?? null, $current->redis()->database()),
                password: $redisPassword,
                prefix: $this->stringOr($redis['prefix'] ?? null, $current->redis()->prefix()),
                timeout: $this->floatOr($redis['timeout'] ?? null, $current->redis()->timeout()),
            ),
        );
    }

    /**
     * Normalizes a scalar value to a non-empty string or preserves the current value.
     */
    private function stringOr(mixed $value, string $current): string
    {
        if (!is_scalar($value)) {
            return $current;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? $current : $normalized;
    }

    /**
     * Normalizes an optional scalar value to a trimmed nullable string.
     */
    private function nullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Converts a numeric value to an integer without accepting arbitrary scalars.
     */
    private function intOr(mixed $value, int $current): int
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

        return $current;
    }

    /**
     * Converts a numeric value to a float without accepting arbitrary scalars.
     */
    private function floatOr(mixed $value, float $current): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $current;
    }

    /**
     * Converts the complete canonical default contribution to its typed runtime representation.
     */
    private function toDefaultConfig(): SessionConfig
    {
        $data = $this->toArray();

        /** @var array{path: string} $native */
        $native = $data['native'];
        /** @var array{path: string} $file */
        $file = $data['file'];
        /** @var array{table: string} $database */
        $database = $data['database'];
        /** @var array{
         *     host: string,
         *     port: int,
         *     database: int,
         *     password: string|null,
         *     prefix: string,
         *     timeout: float
         * } $redis
         */
        $redis = $data['redis'];

        /** @var string $driver */
        $driver = $data['driver'];
        /** @var string $cookie */
        $cookie = $data['cookie'];
        /** @var int $lifetime */
        $lifetime = $data['lifetime'];

        return new SessionConfig(
            driver: $driver,
            cookie: $cookie,
            lifetime: $lifetime,
            native: new SessionNativeConfig($native['path']),
            file: new SessionFileConfig($file['path']),
            database: new SessionDatabaseConfig($database['table']),
            redis: new SessionRedisConfig(
                host: $redis['host'],
                port: $redis['port'],
                database: $redis['database'],
                password: $redis['password'],
                prefix: $redis['prefix'],
                timeout: $redis['timeout'],
            ),
        );
    }
}
