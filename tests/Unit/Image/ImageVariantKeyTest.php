<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageScalePolicy;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Image\Value\ImageVariantKey;
use Lemonade\Framework\Image\Value\ImageVariantMode;
use PHPUnit\Framework\TestCase;

final class ImageVariantKeyTest extends TestCase
{
    public function testKeyIsDeterministicAndIncludesEveryOutputAffectingValue(): void
    {
        $asset = $this->asset('v1');
        $base = $this->definition(
            format: ImageFormat::Png,
            background: ImageBackground::transparent(),
        );
        $key = ImageVariantKey::from($asset, $base)->value();
        self::assertSame($key, ImageVariantKey::from($asset, $base)->value());
        self::assertNotSame($key, ImageVariantKey::from($this->asset('v2'), $base)->value());
        self::assertNotSame($key, ImageVariantKey::from($asset, $this->definition(width: 65))->value());
        self::assertNotSame($key, ImageVariantKey::from($asset, $this->definition(format: ImageFormat::Jpeg))->value());
        self::assertNotSame($key, ImageVariantKey::from($asset, $this->definition(quality: 81))->value());
        self::assertNotSame(
            $key,
            ImageVariantKey::from(
                $asset,
                $this->definition(
                    format: ImageFormat::Png,
                    background: ImageBackground::color('#000000'),
                ),
            )->value(),
        );
        self::assertNotSame($key, ImageVariantKey::from($asset, $this->definition(pipeline: 2))->value());
        self::assertNotSame($key, ImageVariantKey::from($asset, $this->definition(mode: ImageVariantMode::Contain))->value());
        self::assertNotSame($key, ImageVariantKey::from($asset, $this->definition(scalePolicy: ImageScalePolicy::AllowUpscale))->value());
    }

    private function asset(string $version): ImageAsset
    {
        return new ImageAsset('asset', new ImageOriginalReference(ImageOriginalStorage::Storage, 'images/originals/a/original.jpg', $version, ImageFormat::Jpeg, new ImageDimensions(100, 50)));
    }

    private function definition(
        int $width = 64,
        ImageFormat $format = ImageFormat::Jpeg,
        int $quality = 80,
        ?ImageBackground $background = null,
        int $pipeline = 1,
        ImageVariantMode $mode = ImageVariantMode::CenterCover,
        ImageScalePolicy $scalePolicy = ImageScalePolicy::DownscaleOnly,
    ): ImageVariantDefinition {
        return new ImageVariantDefinition(
            new ImageDimensions($width, 64),
            $format,
            ImageQuality::fromInt($quality),
            $background ?? ImageBackground::color('#ffffff'),
            $pipeline,
            $mode,
            $scalePolicy,
        );
    }

    public function testCanonicalRepresentationUsesOneDeterministicBackgroundValue(): void
    {
        $definition = $this->definition(background: ImageBackground::color('#ABCDEF'));
        $canonical = $definition->canonical();

        self::assertSame('#abcdef', $canonical['background']);
        self::assertSame(
            [
                'mode',
                'scale_policy',
                'background',
                'width',
                'height',
                'format',
                'quality',
                'pipeline',
            ],
            array_keys($canonical),
        );
    }
}
