<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Native;

use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Storage\SessionStorageInterface;

final class NativeSession implements SessionInterface
{
    public function __construct(
        private readonly SessionStorageInterface $storage,
    ) {}

    public function start(): void
    {
        $this->storage->start();
    }

    public function started(): bool
    {
        return $this->storage->started();
    }

    public function has(string $key): bool
    {
        return $this->storage->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->storage->set($key, $value);
    }

    public function remove(string $key): void
    {
        $this->storage->remove($key);
    }

    public function clear(): void
    {
        $this->storage->clear();
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->storage->regenerate($deleteOldSession);
    }
}
