<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

/**
 * Defines the Redis operations required by session lifecycle management.
 *
 * @internal
 */
interface RedisSessionClientInterface
{
    /**
     * Opens the configured endpoint for subsequent session operations.
     */
    public function connect(string $host, int $port, float $timeout): bool;

    /**
     * Authenticates the connection with the configured secret.
     */
    public function authenticate(string $password): bool;

    /**
     * Selects the logical database that owns session keys.
     */
    public function selectDatabase(int $database): bool;

    /**
     * Returns a serialized payload or null when the key is absent or expired.
     */
    public function get(string $key): ?string;

    /**
     * Reports whether the key currently exists.
     */
    public function exists(string $key): bool;

    /**
     * Renews an existing key and reports whether the key was touched.
     */
    public function expire(string $key, int $seconds): bool;

    /**
     * Stores a payload with a mandatory expiry.
     */
    public function setex(string $key, int $seconds, string $payload): bool;

    /**
     * Deletes a key and reports whether Redis executed the operation.
     */
    public function delete(string $key): bool;

    /**
     * Releases the underlying Redis connection.
     */
    public function close(): void;
}
