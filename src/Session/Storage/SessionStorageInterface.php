<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

/**
 * Defines persistence and identifier lifecycle operations for session backends
 */
interface SessionStorageInterface
{
    /**
     * Initializes the backend before it serves session values
     */
    public function start(): void;

    /**
     * Reports whether the backend has been initialized for this request
     */
    public function started(): bool;

    /**
     * Checks whether the backend contains a value for the key
     */
    public function has(string $key): bool;

    /**
     * Reads a value from the backend or returns the supplied fallback
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists a value under the key in the active session
     */
    public function set(string $key, mixed $value): void;

    /**
     * Removes the value stored under the key from the active session
     */
    public function remove(string $key): void;

    /**
     * Removes all values from the active session
     */
    public function clear(): void;

    /**
     * Replaces the active identifier and optionally removes its prior record
     */
    public function regenerate(bool $deleteOldSession = true): void;
}
