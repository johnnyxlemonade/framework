<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Flash;

/**
 * Provides keyed transient values stored inside the current session
 */
interface FlashBagInterface
{
    /**
     * Stores a transient value under the key
     */
    public function set(string $key, mixed $value): void;

    /**
     * Returns a transient value without removing it
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Checks whether a transient value exists for the key
     */
    public function has(string $key): bool;

    /**
     * Removes a transient value without returning it
     */
    public function remove(string $key): void;

    /**
     * Returns a transient value and removes it from the bag
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Returns all transient values keyed by their session names
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Removes every transient value from the current session
     */
    public function clear(): void;
}
