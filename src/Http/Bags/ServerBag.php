<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

/**
 * Provides normalized host, scheme, and address access over captured server metadata.
 */
final readonly class ServerBag
{
    /**
     * Stores one server-parameter snapshot without consulting global state.
     *
     * @param array<string, mixed> $items
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * Returns the complete server-parameter snapshot.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Returns one server value or the supplied fallback when absent or null.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Returns HTTP_HOST as text or the supplied fallback for a non-stringable value.
     */
    public function host(string $default = 'localhost'): string
    {
        $value = $this->items['HTTP_HOST'] ?? $default;

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Resolves HTTPS unless the HTTPS server value is empty or explicitly off.
     */
    public function scheme(): string
    {
        $httpsRaw = $this->items['HTTPS'] ?? '';
        $https = (is_scalar($httpsRaw) || $httpsRaw instanceof \Stringable)
            ? strtolower((string) $httpsRaw)
            : '';

        return ($https !== '' && $https !== 'off') ? 'https' : 'http';
    }

    /**
     * Returns REMOTE_ADDR as text or the supplied fallback for a non-stringable value.
     */
    public function ip(?string $default = null): ?string
    {
        $value = $this->items['REMOTE_ADDR'] ?? null;

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }
}
