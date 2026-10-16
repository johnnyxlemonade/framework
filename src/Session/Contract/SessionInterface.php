<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Contract;

/**
 * Provides request-scoped access to keyed session state.
 *
 * Portable values are null, scalars, and arrays composed of portable values. Object restoration
 * is deliberately not part of the storage-neutral contract.
 */
interface SessionInterface
{
    /**
     * Starts the backing session before session state is accessed
     */
    public function start(): void;

    /**
     * Reports whether the backing session has already been initialized
     */
    public function started(): bool;

    /**
     * Checks whether the session contains a value for the key
     */
    public function has(string $key): bool;

    /**
     * Returns the value for a key or the caller-provided fallback
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stores a value under the key for the current session
     */
    public function set(string $key, mixed $value): void;

    /**
     * Removes the value stored under the key
     */
    public function remove(string $key): void;

    /**
     * Removes every value from the current session
     */
    public function clear(): void;

    /**
     * Removes the active record, invalidates its identifier, and expires its browser cookie.
     *
     * A later access starts a new session identity.
     */
    public function destroy(): void;

    /**
     * Replaces the current identifier and can invalidate its prior storage record
     */
    public function regenerate(bool $deleteOldSession = true): void;
}
