<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Contract;

use Lemonade\Framework\Image\Value\ImageReference;

/**
 * Resolves a validated public image reference into a URL for presentation.
 * It does not inspect files or generate image variants.
 */
interface ImageUrlResolverInterface
{
    public function url(ImageReference $reference): string;
}
