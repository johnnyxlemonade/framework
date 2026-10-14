<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

/**
 * Carries the connection and key namespace settings for Redis-backed sessions
 */
final readonly class SessionRedisConfig
{
    /**
     * Defines the Redis endpoint, optional credentials and session key namespace
     */
    public function __construct(
        private string $host,
        private int $port,
        private int $database,
        private ?string $password,
        private string $prefix,
        private float $timeout,
    ) {
    }

    /**
     * Returns the Redis server host.
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * Returns the Redis server TCP port.
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * Returns the Redis logical database number.
     */
    public function database(): int
    {
        return $this->database;
    }

    /**
     * Returns the optional Redis authentication secret.
     */
    public function password(): ?string
    {
        return $this->password;
    }

    /**
     * Returns the namespace prefix for Redis session keys.
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Returns the Redis connection timeout in seconds.
     */
    public function timeout(): float
    {
        return $this->timeout;
    }
}
