<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/** Defines validated presentation attributes for image markup without affecting image processing. */
final readonly class ImageViewOptions
{
    public function __construct(
        public string $alt = '',
        public ?string $class = null,
        public string $loading = 'lazy',
        public ?string $decoding = 'async',
    ) {
        if (!in_array($loading, ['lazy', 'eager', 'auto'], true)) {
            throw new ImageValidationException('Image loading value is invalid.');
        }
        if ($decoding !== null && !in_array($decoding, ['sync', 'async', 'auto'], true)) {
            throw new ImageValidationException('Image decoding value is invalid.');
        }
    }
}
