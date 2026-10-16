<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

use InvalidArgumentException;

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
        private int $lifetime,
        private SessionCookieConfig $cookie,
        private SessionNativeConfig $native,
        private SessionFileConfig $file,
        private SessionDatabaseConfig $database,
        private SessionRedisConfig $redis,
    ) {
        if (trim($driver) === '' || strtolower($driver) !== $driver || preg_match('/^[a-z][a-z0-9_-]*$/D', $driver) !== 1) {
            throw new InvalidArgumentException(
                'Session driver must be a non-empty canonical lowercase identifier.',
            );
        }

        if ($lifetime < 1) {
            throw new InvalidArgumentException('Session lifetime must be positive.');
        }
    }

    /**
     * Returns the selected session storage driver.
     */
    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * Returns the browser identity and cross-site delivery settings.
     */
    public function cookie(): SessionCookieConfig
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
