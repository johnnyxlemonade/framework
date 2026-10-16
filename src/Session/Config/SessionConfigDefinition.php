<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

use InvalidArgumentException;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;

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
            ->cookieName('LEMONADE_SESSION')
            ->cookieSameSite(SessionCookieSameSite::Lax)
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
    public function cookieName(string $cookie): self
    {
        return $this->set('cookie.name', $cookie);
    }

    /**
     * Sets the browser cross-site delivery policy for the session cookie.
     */
    public function cookieSameSite(SessionCookieSameSite $sameSite): self
    {
        return $this->set('cookie.same_site', strtolower($sameSite->value));
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
        $cookie = $this->cookieConfiguration($data);
        $native = is_array($data['native'] ?? null) ? $data['native'] : [];
        $file = is_array($data['file'] ?? null) ? $data['file'] : [];
        $database = is_array($data['database'] ?? null) ? $data['database'] : [];
        $redis = is_array($data['redis'] ?? null) ? $data['redis'] : [];

        $redisPassword = array_key_exists('password', $redis)
            ? $this->nullableString($redis['password'])
            : $current->redis()->password();
        $driver = array_key_exists('driver', $data)
            ? strtolower($this->configuredString($data['driver']))
            : $current->driver();
        $cookieName = array_key_exists('name', $cookie)
            ? $this->configuredString($cookie['name'])
            : $current->cookie()->name();
        $cookieSameSite = array_key_exists('same_site', $cookie)
            ? $this->cookieSameSiteValue($cookie['same_site'])
            : $current->cookie()->sameSite();

        return new SessionConfig(
            driver: $driver,
            lifetime: $this->intOr($data['lifetime'] ?? null, $current->lifetime()),
            cookie: new SessionCookieConfig(
                name: $cookieName,
                sameSite: $cookieSameSite,
            ),
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
     * Returns the configured cookie mapping and rejects the obsolete scalar shape.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function cookieConfiguration(array $data): array
    {
        if (!array_key_exists('cookie', $data)) {
            return [];
        }

        if (is_array($data['cookie'])) {
            return $data['cookie'];
        }

        throw new InvalidArgumentException(
            'Session cookie configuration must be a mapping with name and same_site fields.',
        );
    }

    /**
     * Converts one configured SameSite value to the supported typed policy.
     */
    private function cookieSameSiteValue(mixed $value): SessionCookieSameSite
    {
        $normalized = is_string($value) ? strtolower(trim($value)) : '';

        return match ($normalized) {
            'lax' => SessionCookieSameSite::Lax,
            'strict' => SessionCookieSameSite::Strict,
            'none' => SessionCookieSameSite::None,
            default => throw new InvalidArgumentException(sprintf(
                'Invalid session cookie same_site value "%s". Expected one of: lax, strict, none.',
                is_scalar($value) ? (string) $value : get_debug_type($value),
            )),
        };
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
     * Normalizes an explicitly configured scalar and leaves semantic validation to its value object.
     */
    private function configuredString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
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

        /** @var array{name: string, same_site: string} $cookie */
        $cookie = $data['cookie'];
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
        /** @var int $lifetime */
        $lifetime = $data['lifetime'];

        return new SessionConfig(
            driver: $driver,
            lifetime: $lifetime,
            cookie: new SessionCookieConfig(
                name: $cookie['name'],
                sameSite: $this->cookieSameSiteValue($cookie['same_site']),
            ),
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
