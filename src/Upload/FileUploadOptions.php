<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

final readonly class FileUploadOptions
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private string $targetDirectory,
        private string $targetRelativeDirectory,
        private int $maxBytes = 10_485_760,
        private array $allowedMimeTypes = [],
        private array $allowedExtensions = [],
    ) {
    }

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
