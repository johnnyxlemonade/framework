<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

use function max;
use function time;

final class CacheItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value = null,
        private readonly bool $hit = false,
        private ?DateTimeImmutable $expiresAt = null,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        if (!$this->hit) {
            return false;
        }

        if ($this->expiresAt === null) {
            return true;
        }

        return $this->expiresAt->getTimestamp() > time();
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($expiration)
            : null;

        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;

            return $this;
        }

        if ($time instanceof DateInterval) {
            $this->expiresAt = (new DateTimeImmutable())->add($time);

            return $this;
        }

        $expiresAt = (new DateTimeImmutable())->modify('+' . max(0, $time) . ' seconds');

        if ($expiresAt === false) {
            $this->expiresAt = null;

            return $this;
        }

        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function expiresAtDate(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
