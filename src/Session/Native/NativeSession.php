<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Native;

use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Storage\SessionStorageInterface;

/**
 * Delegates the public session contract to the configured storage implementation
 */
final readonly class NativeSession implements SessionInterface
{
    /**
     * Connects the facade to the configured session storage implementation
     */
    public function __construct(
        private SessionStorageInterface $storage,
    ) {
    }

    /**
     * Starts the delegated storage before any values are read or written
     */
    public function start(): void
    {
        $this->storage->start();
    }

    /**
     * Reports the delegated storage initialization state
     */
    public function started(): bool
    {
        return $this->storage->started();
    }

    /**
     * Checks the delegated storage for a value under the key
     */
    public function has(string $key): bool
    {
        return $this->storage->has($key);
    }

    /**
     * Reads a delegated value or returns the supplied fallback
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage->get($key, $default);
    }

    /**
     * Persists a delegated value under the key
     */
    public function set(string $key, mixed $value): void
    {
        $this->storage->set($key, $value);
    }

    /**
     * Removes a delegated value under the key
     */
    public function remove(string $key): void
    {
        $this->storage->remove($key);
    }

    /**
     * Clears all values held by the delegated storage
     */
    public function clear(): void
    {
        $this->storage->clear();
    }

    /**
     * Destroys the delegated identity and permits a later access to start a new one.
     */
    public function destroy(): void
    {
        $this->storage->destroy();
    }

    /**
     * Regenerates the identifier through the delegated storage implementation
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->storage->regenerate($deleteOldSession);
    }
}
