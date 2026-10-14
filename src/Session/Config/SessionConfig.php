<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

/**
 * Carries the resolved settings used to construct the configured session storage
 */
final readonly class SessionConfig
{
    /**
     * Defines the storage driver, transport cookie, retention period and driver-specific settings
     */
    public function __construct(
        private string $driver,
        private string $cookie,
        private int $lifetime,
        private SessionNativeConfig $native,
        private SessionFileConfig $file,
        private SessionDatabaseConfig $database,
        private SessionRedisConfig $redis,
    ) {
    }

    /**
     * Returns the selected session storage driver.
     */
    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * Returns the browser cookie name carrying the session identifier.
     */
    public function cookie(): string
    {
        return $this->cookie;
    }

    /**
     * Returns the shared cookie and storage retention period in seconds.
     */
    public function lifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * Returns the native PHP session storage settings.
     */
    public function native(): SessionNativeConfig
    {
        return $this->native;
    }

    /**
     * Returns the file-backed session storage settings.
     */
    public function file(): SessionFileConfig
    {
        return $this->file;
    }

    /**
     * Returns the database-backed session storage settings.
     */
    public function database(): SessionDatabaseConfig
    {
        return $this->database;
    }

    /**
     * Returns the Redis-backed session storage settings.
     */
    public function redis(): SessionRedisConfig
    {
        return $this->redis;
    }
}
