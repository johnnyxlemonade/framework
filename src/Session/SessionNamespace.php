<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session;

use InvalidArgumentException;
use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Isolates a logical key space inside one parent session without owning its lifecycle.
 */
final readonly class SessionNamespace
{
    private const string SESSION_KEY_PREFIX = '_namespace:';

    private string $sessionKey;

    /**
     * Connects a validated logical namespace to the parent session's shared lifecycle.
     *
     * @throws InvalidArgumentException when the namespace name is not a readable identifier
     */
    public function __construct(
        private SessionInterface $session,
        string $name,
    ) {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid session namespace name "%s".',
                $name,
            ));
        }

        $this->sessionKey = self::SESSION_KEY_PREFIX . $name;
    }

    /**
     * Checks whether this namespace contains a value for the key.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->read());
    }

    /**
     * Returns a namespaced value or the caller-provided fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->read();

        return array_key_exists($key, $values)
            ? $values[$key]
            : $default;
    }

    /**
     * Stores or replaces one value without changing other namespaces.
     */
    public function set(string $key, mixed $value): void
    {
        $values = $this->read();
        $values[$key] = $value;

        $this->session->set($this->sessionKey, $values);
    }

    /**
     * Removes one value while preserving the rest of this namespace.
     */
    public function remove(string $key): void
    {
        $values = $this->read();

        unset($values[$key]);

        $this->session->set($this->sessionKey, $values);
    }

    /**
     * Returns all normalized values without mutating this namespace.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->read();
    }

    /**
     * Removes this complete namespace without affecting other parent session data.
     */
    public function clear(): void
    {
        $this->session->remove($this->sessionKey);
    }

    /**
     * Normalizes the stored payload to string-keyed namespace data.
     *
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $values = $this->session->get($this->sessionKey, []);

        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
