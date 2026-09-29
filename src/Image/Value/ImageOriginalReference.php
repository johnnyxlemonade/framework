<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Points to a versioned persistent source without exposing an absolute path or requiring public delivery.
 */
final readonly class ImageOriginalReference
{
    /**
     * Creates a safe versioned source reference without exposing an absolute path
     */
    public function __construct(
        private ImageOriginalStorage $storage,
        private string $relativePath,
        private string $sourceVersion,
        private ImageFormat $format,
        private ImageDimensions $dimensions,
    ) {
        if (
            $relativePath === ''
            || str_starts_with($relativePath, '/')
            || str_contains($relativePath, '\\')
            || preg_match('#(^|/)\.\.?(/|$)#', $relativePath) === 1
            || preg_match('/^[A-Za-z0-9_-]+$/', $sourceVersion) !== 1
        ) {
            throw new ImageValidationException('Original reference must use safe relative storage and version values.');
        }
    }

    /**
     * Returns the storage boundary that owns the persistent source
     */
    public function storage(): ImageOriginalStorage
    {
        return $this->storage;
    }

    /**
     * Returns the validated path relative to the selected storage boundary
     */
    public function relativePath(): string
    {
        return $this->relativePath;
    }

    /**
     * Returns the immutable source version used to isolate derived variants
     */
    public function sourceVersion(): string
    {
        return $this->sourceVersion;
    }

    /**
     * Returns the decoded bitmap format expected for the persistent source
     */
    public function format(): ImageFormat
    {
        return $this->format;
    }

    /**
     * Returns the validated dimensions recorded for the persistent source
     */
    public function dimensions(): ImageDimensions
    {
        return $this->dimensions;
    }
}
