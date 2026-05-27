<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

final class FileBag
{
    /**
     * @param array<string, mixed> $files
     */
    public function __construct(private readonly array $files = []) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->files;
    }

    public function get(string $name): mixed
    {
        return $this->files[$name] ?? null;
    }
}
