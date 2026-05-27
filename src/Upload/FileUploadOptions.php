<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

final class FileUploadOptions
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private readonly string $targetDirectory,
        private readonly string $targetRelativeDirectory,
        private readonly int $maxBytes = 10_485_760,
        private readonly array $allowedMimeTypes = [],
        private readonly array $allowedExtensions = [],
    ) {}

    public function targetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function targetRelativeDirectory(): string
    {
        return $this->targetRelativeDirectory;
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return $this->allowedExtensions;
    }
}
