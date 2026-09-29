<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Selects the geometry policy used to place an original on a derived variant canvas.
 *
 * Center cover fills the canvas by cropping excess source area. Contain preserves
 * the whole source and leaves the definition's explicit background in unused space.
 */
enum ImageVariantMode: string
{
    case CenterCover = 'center-cover';
    case Contain = 'contain';
}
