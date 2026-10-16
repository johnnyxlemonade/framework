<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

/**
 * Exposes uploaded-file input without interpreting or restructuring its values.
 */
final readonly class FileBag
{
    /**
     * Stores the uploaded-file map for read-only access.
     *
     * @param array<string, mixed> $files
     */
    public function __construct(private array $files = [])
    {
    }

    /**
     * Returns the complete uploaded-file map.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->files;
    }

    /**
     * Returns one uploaded-file value or null when the name is absent.
     */
    public function get(string $name): mixed
    {
        return $this->files[$name] ?? null;
    }
}
