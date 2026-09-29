<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Contract;

use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;

/**
 * Renders a complete variant in one backend-controlled pass, without exposing intermediate mutable images.
 */
interface ImageVariantRendererInterface
{
    /**
     * Renders one encoded variant directly from its persistent source reference
     */
    public function render(
        ImageSource $source,
        ImageOriginalReference $original,
        ImageVariantDefinition $definition,
    ): EncodedImage;
}
