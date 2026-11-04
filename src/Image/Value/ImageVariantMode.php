<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Selects the geometry policy used to place an original on a derived variant canvas.
 *
 * Center cover fills the canvas by cropping excess source area. Contain preserves
 * the whole source on a fixed canvas; fit uses only the pixels needed for the
 * proportional image inside the configured maximum dimensions.
 */
enum ImageVariantMode: string
{
    case CenterCover = 'center-cover';
    case Contain = 'contain';
    case Fit = 'fit';
}
