<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Image\Value\ImageVariantKey;

/**
 * Resolves bounded persistent-original and public-variant paths without making originals public by default
 */
final readonly class ImageVariantPathResolver
{
    public function __construct(
        private ApplicationContext     $context,
        private DirectoryPathGenerator $directories,
    ) {
    }

    /**
     * Resolves the physical filesystem path of the asset's persistent original
     */
    public function originalPath(ImageAsset $asset): string
    {
        $original = $asset->original();

        return match ($original->storage()) {
            ImageOriginalStorage::Upload => $this->context->uploadPath($original->relativePath()),
            ImageOriginalStorage::Storage => $this->context->storagePath($original->relativePath()),
        };
    }

    /**
     * Creates the canonical persistent-original reference; callers choose its public or private storage boundary.
     */
    public function originalReference(
        string $assetId,
        string $sourceVersion,
        ImageOriginalStorage $storage,
        ImageFormat $format,
        ImageDimensions $dimensions,
    ): ImageOriginalReference {
        $shard = rtrim($this->directories->shard($assetId), '/');
        $relativePath = sprintf(
            'images/originals/%s/%s/%s/original.%s',
            $shard,
            $assetId,
            $sourceVersion,
            $format->extension(),
        );

        return new ImageOriginalReference(
            $storage,
            $relativePath,
            $sourceVersion,
            $format,
            $dimensions,
        );
    }

    /**
     * Resolves the physical filesystem path of a derived public variant
     */
    public function variantPath(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
    ): string {
        $relativePath = $this->variantRelativePath($asset, $definition);

        return $this->context->uploadPath($relativePath);
    }

    /**
     * Resolves the exact public directory owned by one immutable source version.
     */
    public function variantDirectory(ImageAsset $asset): string
    {
        $assetId = $asset->id();
        $sourceVersion = $asset->original()->sourceVersion();
        $shard = rtrim($this->directories->shard($assetId), '/');

        return $this->context->uploadPath(sprintf(
            'images/variants/%s/%s/%s',
            $shard,
            $assetId,
            $sourceVersion,
        ));
    }

    /**
     * Resolves a non-public lock path for one deterministic variant.
     */
    public function variantLockPath(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
    ): string {
        $assetId = $asset->id();
        $sourceVersion = $asset->original()->sourceVersion();
        $key = ImageVariantKey::from($asset, $definition);
        $shard = rtrim($this->directories->shard($assetId), '/');

        return $this->context->storagePath(sprintf(
            'cache/images/variant-locks/%s/%s/%s/%s.lock',
            $shard,
            $assetId,
            $sourceVersion,
            $key->value(),
        ));
    }

    /**
     * Creates the public image reference for a derived variant
     */
    public function reference(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
    ): ImageReference {
        $relativePath = $this->variantRelativePath($asset, $definition);

        return new ImageReference(
            $relativePath,
            $definition->dimensions(),
            $definition->format(),
        );
    }

    private function variantRelativePath(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
    ): string {
        $assetId = $asset->id();
        $sourceVersion = $asset->original()->sourceVersion();
        $format = $definition->format();
        $key = ImageVariantKey::from($asset, $definition);
        $shard = rtrim($this->directories->shard($assetId), '/');

        return sprintf(
            'images/variants/%s/%s/%s/%s.%s',
            $shard,
            $assetId,
            $sourceVersion,
            $key->value(),
            $format->extension(),
        );
    }
}
