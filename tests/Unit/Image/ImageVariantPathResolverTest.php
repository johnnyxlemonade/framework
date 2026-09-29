<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use PHPUnit\Framework\TestCase;

final class ImageVariantPathResolverTest extends TestCase
{
    public function testCanonicalOriginalAndVariantPathsUseApplicationContextAndSharding(): void
    {
        $resolver = new ImageVariantPathResolver($this->context(), new DirectoryPathGenerator());
        $original = $resolver->originalReference('asset-1', 'v1', ImageOriginalStorage::Storage, ImageFormat::Png, new ImageDimensions(100, 50));
        $asset = new ImageAsset('asset-1', $original);
        $definition = new ImageVariantDefinition(new ImageDimensions(64, 64), ImageFormat::Webp, ImageQuality::fromInt(80));
        $path = $resolver->variantPath($asset, $definition);
        $shard = rtrim((new DirectoryPathGenerator())->shard('asset-1'), '/');
        self::assertStringContainsString('/storage/images/originals/' . $shard . '/asset-1/v1/original.png', $resolver->originalPath($asset));
        self::assertStringContainsString('/public/uploads/images/variants/' . $shard . '/asset-1/v1/', $path);
        self::assertStringEndsWith('.webp', $path);
        self::assertSame($path, $resolver->variantPath($asset, $definition));
        self::assertStringStartsNotWith('/', $resolver->reference($asset, $definition)->publicPath());
    }

    public function testUploadOriginalUsesUploadBoundary(): void
    {
        $resolver = new ImageVariantPathResolver($this->context(), new DirectoryPathGenerator());
        $original = $resolver->originalReference('asset-1', 'v1', ImageOriginalStorage::Upload, ImageFormat::Jpeg, new ImageDimensions(1, 1));
        self::assertStringContainsString('/public/uploads/images/originals/', $resolver->originalPath(new ImageAsset('asset-1', $original)));
    }

    private function context(): ApplicationContext { return new ApplicationContext(Environment::Testing, new Path('/framework', '/framework/public'), DebugMode::disabled()); }
}
