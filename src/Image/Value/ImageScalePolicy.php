<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Determines whether rendering may enlarge an original image beyond its native pixels.
 *
 * DownscaleOnly keeps smaller originals at their native size. AllowUpscale permits
 * both geometry modes to enlarge an original to fit their target geometry.
 */
enum ImageScalePolicy: string
{
    case AllowUpscale = 'allow-upscale';
    case DownscaleOnly = 'downscale-only';
}
