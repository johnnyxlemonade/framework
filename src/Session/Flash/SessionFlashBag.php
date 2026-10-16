<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Flash;

use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\SessionNamespace;

/**
 * Stores flash values in one normalized namespace of the current session
 */
final readonly class SessionFlashBag implements FlashBagInterface
{
    private const string NAMESPACE_NAME = 'flash';

    private SessionNamespace $namespace;

    /**
     * Connects the bag to the session that persists its transient values
     */
    public function __construct(
        SessionInterface $session,
    ) {
        $this->namespace = new SessionNamespace($session, self::NAMESPACE_NAME);
    }

    /**
     * Stores or replaces a flash value in the dedicated session namespace
     */
    public function set(string $key, mixed $value): void
    {
        $this->namespace->set($key, $value);
    }

    /**
     * Returns a flash value while leaving it available for later reads
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->namespace->get($key, $default);
    }

    /**
     * Checks the normalized flash namespace for the key
     */
    public function has(string $key): bool
    {
        return $this->namespace->has($key);
    }

    /**
     * Removes a flash value without returning it
     */
    public function remove(string $key): void
    {
        $this->namespace->remove($key);
    }

    /**
     * Returns a flash value and removes it during the current request.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        if (!$this->namespace->has($key)) {
            return $default;
        }

        $value = $this->namespace->get($key);
        $this->namespace->remove($key);

        return $value;
    }

    /**
     * Returns all normalized flash values without mutating the bag
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->namespace->all();
    }

    /**
     * Removes the complete flash namespace from the session
     */
    public function clear(): void
    {
        $this->namespace->clear();
    }
}
