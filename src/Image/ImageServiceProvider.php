<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Contract\ImageFileWriterInterface;
use Lemonade\Framework\Image\Contract\ImageProcessorInterface;
use Lemonade\Framework\Image\Contract\ImageUrlResolverInterface;
use Lemonade\Framework\Image\Contract\ImageVariantRendererInterface;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageEncoder;
use Lemonade\Framework\Image\Gd\GdImageProcessor;
use Lemonade\Framework\Image\Gd\GdImageVariantRenderer;

/**
 * Registers singleton image infrastructure shared by uploads, application services, and views
 */
final class ImageServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers shared image processing, URL, variant, and view integration services
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(GdCapabilities::class, static function (): GdCapabilities {
            $capabilities = new GdCapabilities();
            $capabilities->initialize();
            return $capabilities;
        });
        $container->singleton(ImageProcessorInterface::class, GdImageProcessor::class);
        $container->singleton(ImageEncoderInterface::class, GdImageEncoder::class);
        $container->singleton(ImageFileWriterInterface::class, FilesystemImageFileWriter::class);
        $container->singleton(ImageUrlResolverInterface::class, PublicUploadImageUrlResolver::class);
        $container->singleton(ImageVariantRendererInterface::class, GdImageVariantRenderer::class);
        $container->singleton(ImageVariantPathResolver::class, ImageVariantPathResolver::class);
        $container->singleton(ImageVariantCache::class, ImageVariantCache::class);
        $container->singleton(ImageVariantGenerator::class, ImageVariantGenerator::class);
        $container->singleton(ImageViewHelper::class, ImageViewHelper::class);
    }
}
