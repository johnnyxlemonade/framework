<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Flash;

use Lemonade\Framework\Session\Contract\SessionInterface;

final class SessionFlashBag implements FlashBagInterface
{
    private const SESSION_KEY = '_flash';

    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function set(string $key, mixed $value): void
    {
        $flash = $this->read();

        $flash[$key] = $value;

        $this->session->set(self::SESSION_KEY, $flash);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $flash = $this->read();

        return array_key_exists($key, $flash)
            ? $flash[$key]
            : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->read());
    }

    public function remove(string $key): void
    {
        $flash = $this->read();

        unset($flash[$key]);

        $this->session->set(self::SESSION_KEY, $flash);
    }

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

    public function all(): array
    {
        return $this->read();
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    /**
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
