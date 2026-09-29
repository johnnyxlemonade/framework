<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Image\Contract\ImageVariantRendererInterface;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;

/**
 * Coordinates one cache-aware decode, render, encode, and atomic publication flow for a derived variant
 */
final readonly class ImageVariantGenerator
{
    public function __construct(
        private readonly ImageVariantCache $cache,
        private readonly ImageVariantPathResolver $paths,
        private readonly ImageVariantRendererInterface $renderer,
    ) {
    }

    /**
     * Generates or retrieves the public reference for one deterministic derived variant
     */
    public function generate(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
    ): ImageReference {
        $source = ImageSource::fromFile(
            $this->paths->originalPath($asset),
        );

        return $this->cache->remember(
            $asset,
            $definition,
            fn() => $this->renderer->render(
                $source,
                $asset->original(),
                $definition,
            ),
        );
    }
}
