<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Flash;

use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Stores flash values in one normalized namespace of the current session
 */
final readonly class SessionFlashBag implements FlashBagInterface
{
    private const string SESSION_KEY = '_flash';

    /**
     * Connects the bag to the session that persists its transient values
     */
    public function __construct(
        private SessionInterface $session,
    ) {
    }

    /**
     * Stores or replaces a flash value in the dedicated session namespace
     */
    public function set(string $key, mixed $value): void
    {
        $flash = $this->read();

        $flash[$key] = $value;

        $this->session->set(self::SESSION_KEY, $flash);
    }

    /**
     * Returns a flash value while leaving it available for later reads
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $flash = $this->read();

        return array_key_exists($key, $flash)
            ? $flash[$key]
            : $default;
    }

    /**
     * Checks the normalized flash namespace for the key
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->read());
    }

    /**
     * Removes a flash value without returning it
     */
    public function remove(string $key): void
    {
        $flash = $this->read();

        unset($flash[$key]);

        $this->session->set(self::SESSION_KEY, $flash);
    }

    /**
     * Returns a flash value and removes it atomically from the bag
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $flash = $this->read();

        if (!array_key_exists($key, $flash)) {
            return $default;
        }

        $value = $flash[$key];

        unset($flash[$key]);

        $this->session->set(self::SESSION_KEY, $flash);

        return $value;
    }

    /**
     * Returns all normalized flash values without mutating the bag
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->read();
    }

    /**
     * Removes the complete flash namespace from the session
     */
    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    /**
     * Reads only string-keyed flash values from the session namespace
     *
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $flash = $this->session->get(self::SESSION_KEY, []);

        if (!is_array($flash)) {
            return [];
        }

        $normalized = [];

        foreach ($flash as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
