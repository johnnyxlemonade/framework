<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

use function max;
use function time;

/**
 * Holds one PSR-6 cache value together with its original hit state and optional expiration.
 *
 * Value and expiration are mutable as required by PSR-6, while key and hit origin remain fixed.
 */
final class CacheItem implements CacheItemInterface
{
    /**
     * Captures the key, value, lookup result, and optional absolute expiration.
     */
    public function __construct(
        private readonly string $key,
        private mixed $value = null,
        private readonly bool $hit = false,
        private ?DateTimeImmutable $expiresAt = null,
    ) {
    }

    /**
     * Returns the immutable key associated with this item.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Returns the value currently held by this item.
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * Reports a hit only when the item originated as one and has not expired.
     */
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

    /**
     * Replaces the held value and returns this item for fluent mutation.
     */
    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Sets an absolute expiration using an immutable copy, or clears it with null.
     */
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($expiration)
            : null;

        return $this;
    }

    /**
     * Sets expiration relative to now, clamps negative seconds to zero, or clears it with null.
     */
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

    /**
     * Exposes the normalized absolute expiration used by framework pool implementations.
     */
    public function expiresAtDate(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
