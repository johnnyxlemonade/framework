<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use Redis;
use RuntimeException;

/**
 * Normalizes phpredis return values for the session storage lifecycle.
 *
 * @internal
 */
final class PhpRedisSessionClient implements RedisSessionClientInterface
{
    private Redis $redis;

    /**
     * Creates the native client only when the optional Redis extension is available.
     */
    public function __construct()
    {
        if (!class_exists(Redis::class)) {
            throw new RuntimeException(
                'Redis extension is not installed. Install ext-redis to use redis session storage.',
            );
        }

        $this->redis = new Redis();
    }

    /**
     * Opens the native Redis connection.
     */
    public function connect(string $host, int $port, float $timeout): bool
    {
        return $this->redis->connect($host, $port, $timeout) === true;
    }

    /**
     * Authenticates the native Redis connection.
     */
    public function authenticate(string $password): bool
    {
        return $this->redis->auth($password) === true;
    }

    /**
     * Selects the configured logical Redis database.
     */
    public function selectDatabase(int $database): bool
    {
        return $this->redis->select($database) === true;
    }

    /**
     * Returns only string payloads and maps absent keys to null.
     */
    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);

        return is_string($value) ? $value : null;
    }

    /**
     * Normalizes phpredis integer and boolean existence replies.
     */
    public function exists(string $key): bool
    {
        $result = $this->redis->exists($key);

        return is_int($result) ? $result > 0 : $result === true;
    }

    /**
     * Reports whether Redis renewed the requested key.
     */
    public function expire(string $key, int $seconds): bool
    {
        return $this->redis->expire($key, $seconds) === true;
    }

    /**
     * Stores the payload only when phpredis confirms success.
     */
    public function setex(string $key, int $seconds, string $payload): bool
    {
        return $this->redis->setex($key, $seconds, $payload) === true;
    }

    /**
     * Treats both deletion and an already absent key as a successful command.
     */
    public function delete(string $key): bool
    {
        return is_int($this->redis->del($key));
    }

    /**
     * Closes the native connection without exposing its return convention.
     */
    public function close(): void
    {
        $this->redis->close();
    }
}
