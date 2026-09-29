<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Describes validated bitmap dimensions with strictly positive width and height
 */
final readonly class ImageDimensions
{
    /**
     * Creates strictly positive width and height bounds for image processing
     */
    public function __construct(public int $width, public int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new ImageValidationException('Image dimensions must be positive.');
        }
    }

    /**
     * Returns the total pixel budget represented by the dimensions
     */
    public function pixels(): int
    {
        return $this->width * $this->height;
    }
}
