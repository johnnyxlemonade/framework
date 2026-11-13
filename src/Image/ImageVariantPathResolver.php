<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Image\Value\ImageVariantKey;

/**
 * Separates persistent private originals from disposable image variants
 */
final readonly class ImageVariantPathResolver
{
    /**
     * Connects the application storage roots and shared directory sharding
     */
    public function __construct(
        private ApplicationContext $context,
        private DirectoryPathGenerator $directories,
    ) {
    }

    /**
     * Returns the single physical path used to write, read, and delete an original
     */
    public function originalPath(ImageAsset $asset): string
    {
        return $this->context->uploadPath($asset->original()->relativePath());
    }

    /**
     * Creates a persistent original reference without changing its asset or source identity
     */
    public function originalReference(
        string $assetId,
        string $sourceVersion,
        ImageFormat $format,
        ImageDimensions $dimensions,
    ): ImageOriginalReference {
        return new ImageOriginalReference(
            relativePath: sprintf(
                'images/originals/%s%s/%s/original.%s',
                $this->directories->shard($assetId),
                $assetId,
                $sourceVersion,
                $format->extension(),
            ),
            sourceVersion: $sourceVersion,
            format: $format,
            dimensions: $dimensions,
        );
    }

    /**
     * Returns the private cache path of one deterministic variant
     */
    public function variantPath(ImageAsset $asset, ImageVariantDefinition $definition): string
    {
        return sprintf(
            '%s/%s.%s',
            $this->variantDirectory($asset),
            ImageVariantKey::from($asset, $definition)->value(),
            $definition->format()->extension(),
        );
    }

    /**
     * Returns the cache directory owned by one immutable source version
     */
    public function variantDirectory(ImageAsset $asset): string
    {
        return $this->context->resolveCachePath(sprintf(
            'images/%s%s/%s',
            $this->directories->shard($asset->id()),
            $asset->id(),
            $asset->original()->sourceVersion(),
        ));
    }

    /**
     * Returns the existing private generation lock for one deterministic variant
     */
    public function variantLockPath(ImageAsset $asset, ImageVariantDefinition $definition): string
    {
        return $this->context->resolveCachePath(sprintf(
            'images/variant-locks/%s%s/%s/%s.lock',
            $this->directories->shard($asset->id()),
            $asset->id(),
            $asset->original()->sourceVersion(),
            ImageVariantKey::from($asset, $definition)->value(),
        ));
    }

    /**
     * Describes a cached variant without exposing a direct public filesystem URL
     */
    public function reference(ImageAsset $asset, ImageVariantDefinition $definition): ImageReference
    {
        return new ImageReference(
            publicPath: null,
            dimensions: $definition->outputDimensions($asset->original()->dimensions()),
            format: $definition->format(),
        );
    }
}
