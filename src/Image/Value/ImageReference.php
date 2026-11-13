<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Describes image dimensions and format; optional direct paths belong to caller-owned static images
 */
final readonly class ImageReference
{
    /**
     * Validates the optional public-relative path without exposing private cache locations
     */
    public function __construct(
        private ?string $publicPath,
        private ImageDimensions $dimensions,
        private ImageFormat $format,
    ) {
        $path = $publicPath === null ? null : trim($publicPath);
        if ($path !== null && ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1)) {
            throw new ImageValidationException('Image reference must be a safe public-relative path.');
        }
    }

    /**
     * Returns the direct public path or rejects direct linking to a private variant
     */
    public function publicPath(): string
    {
        if ($this->publicPath === null) {
            throw new \LogicException('Private image variants must be delivered through an application endpoint.');
        }

        return $this->publicPath;
    }

    /**
     * Returns the actual image dimensions
     */
    public function dimensions(): ImageDimensions
    {
        return $this->dimensions;
    }

    /**
     * Returns the encoded image format
     */
    public function format(): ImageFormat
    {
        return $this->format;
    }
}
