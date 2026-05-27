<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

final class HeaderBag
{
    /**
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->headers;
    }

    public function has(string $name): bool
    {
        return array_key_exists($this->normalize($name), $this->headers);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->headers[$this->normalize($name)] ?? $default;
    }

    public function set(string $name, string $value): void
    {
        $this->headers[$this->normalize($name)] = $value;
    }

    private function normalize(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', strtolower(str_replace('_', '-', $name)))));
    }
}
