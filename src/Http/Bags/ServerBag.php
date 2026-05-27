<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

final class ServerBag
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private readonly array $items = []) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function host(string $default = 'localhost'): string
    {
        $value = $this->items['HTTP_HOST'] ?? $default;

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    public function scheme(): string
    {
        $httpsRaw = $this->items['HTTPS'] ?? '';
        $https = (is_scalar($httpsRaw) || $httpsRaw instanceof \Stringable)
            ? strtolower((string) $httpsRaw)
            : '';

        return ($https !== '' && $https !== 'off') ? 'https' : 'http';
    }

    public function ip(?string $default = null): ?string
    {
        $value = $this->items['REMOTE_ADDR'] ?? null;

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }
}
