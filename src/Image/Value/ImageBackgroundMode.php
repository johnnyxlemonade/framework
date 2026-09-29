<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Identifies whether unused variant-canvas pixels remain transparent or use an opaque color.
 */
enum ImageBackgroundMode: string
{
    case Transparent = 'transparent';
    case Color = 'color';
}
