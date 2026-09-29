<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/** Defines a positive crop area; containment within a source image is validated by the processor. */
final readonly class CropRectangle
{
    public function __construct(public int $x, public int $y, public ImageDimensions $dimensions)
    {
        if ($x < 0 || $y < 0) {
            throw new ImageValidationException('Crop origin must not be negative.');
        }
    }
}
