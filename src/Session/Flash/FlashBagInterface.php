<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Flash;

interface FlashBagInterface
{
    public function set(string $key, mixed $value): void;

    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function remove(string $key): void;

    /**
     * Vrátí hodnotu a okamžitě ji odstraní.
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function clear(): void;
}
