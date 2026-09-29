<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Image\Exception\ImageValidationException;
use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageBackgroundMode;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use PHPUnit\Framework\TestCase;

final class ImageVariantDefinitionTest extends TestCase
{
    public function testDefaultBackgroundIsTransparentForAlphaCapableFormats(): void
    {
        $definition = new ImageVariantDefinition(
            new ImageDimensions(64, 64),
            ImageFormat::Png,
            ImageQuality::fromInt(80),
        );

        self::assertSame(ImageBackgroundMode::Transparent, $definition->background()->mode());
        self::assertNull($definition->background()->colorValue());
    }

    public function testJpegRejectsTransparentBackground(): void
    {
        $this->expectException(ImageValidationException::class);

        new ImageVariantDefinition(
            new ImageDimensions(64, 64),
            ImageFormat::Jpeg,
            ImageQuality::fromInt(80),
            ImageBackground::transparent(),
        );
    }
}
