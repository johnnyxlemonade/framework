<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Image\Exception\ImageValidationException;
use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageBackgroundMode;
use PHPUnit\Framework\TestCase;

final class ImageBackgroundTest extends TestCase
{
    public function testTransparentFactoryHasNoColorValue(): void
    {
        $background = ImageBackground::transparent();

        self::assertSame(ImageBackgroundMode::Transparent, $background->mode());
        self::assertNull($background->colorValue());
        self::assertSame('transparent', $background->canonical());
    }

    public function testColorFactoryNormalizesTheColorValue(): void
    {
        $background = ImageBackground::color('#AbCdEf');

        self::assertSame(ImageBackgroundMode::Color, $background->mode());
        self::assertSame('#abcdef', $background->colorValue());
        self::assertSame('#abcdef', $background->canonical());
    }

    public function testColorFactoryRejectsNonCanonicalHexValues(): void
    {
        $this->expectException(ImageValidationException::class);

        ImageBackground::color('fff');
    }
}
