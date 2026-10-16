<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

/**
 * Stores HTTP header values under case-insensitive canonical names.
 */
final class HeaderBag
{
    /**
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Initializes the bag while normalizing every supplied header name.
     *
     * @param array<string, string> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * Returns all headers keyed by their canonical names.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * Reports whether a header exists regardless of name casing or underscores.
     */
    public function has(string $name): bool
    {
        return array_key_exists($this->normalize($name), $this->headers);
    }

    /**
     * Returns a header value by normalized name or the supplied fallback.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->headers[$this->normalize($name)] ?? $default;
    }

    /**
     * Replaces the value stored under a normalized header name.
     */
    public function set(string $name, string $value): void
    {
        $this->headers[$this->normalize($name)] = $value;
    }

    private function normalize(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', strtolower(str_replace('_', '-', $name)))));
    }
}
