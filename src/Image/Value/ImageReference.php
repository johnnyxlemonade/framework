<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Describes a publicly addressable image with its actual format and dimensions.
 * The reference rejects absolute and traversal paths, so it never exposes internal storage paths.
 */
final readonly class ImageReference
{
    public function __construct(
        private string $publicPath,
        private ImageDimensions $dimensions,
        private ImageFormat $format,
    ) {
        $path = trim($publicPath);
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1) {
            throw new ImageValidationException('Image reference must be a safe public-relative path.');
        }
    }

    public function publicPath(): string
    {
        return $this->publicPath;
    }

    public function dimensions(): ImageDimensions
    {
        return $this->dimensions;
    }

    public function format(): ImageFormat
    {
        return $this->format;
    }
}
