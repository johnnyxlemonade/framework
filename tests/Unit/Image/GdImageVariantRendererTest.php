<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use GdImage;
use Lemonade\Framework\Image\Exception\ImageDecodeException;
use Lemonade\Framework\Image\Exception\ImageValidationException;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageVariantRenderer;
use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageScalePolicy;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Image\Value\ImageVariantMode;
use PHPUnit\Framework\TestCase;

final class GdImageVariantRendererTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-variant-' . uniqid('', true);
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->root . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->root);
    }

    public function testCenterCoverProducesSquareForLandscapeAndPortrait(): void
    {
        foreach ([[200, 100], [100, 200]] as [$width, $height]) {
            $result = $this->renderer()->render(
                ImageSource::fromFile($this->jpeg($width, $height)),
                $this->original($width, $height),
                $this->definition(),
            );

            self::assertEquals(new ImageDimensions(64, 64), $result->dimensions());
        }
    }

    public function testContainRendersLandscapeOnTheExactTargetCanvas(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->solidPng(1600, 900, 255, 0, 0)),
            $this->original(1600, 900),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Png,
                mode: ImageVariantMode::Contain,
                scalePolicy: ImageScalePolicy::AllowUpscale,
            ),
        );

        self::assertEquals(new ImageDimensions(800, 450), $result->dimensions());
        $this->assertPixelColor($result->contents(), 0, 0, 255, 0, 0);
        $this->assertPixelColor($result->contents(), 799, 449, 255, 0, 0);
    }

    public function testContainKeepsBothPortraitEdgesVisibleAndLeavesTransparentPngBars(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->verticalBandsPng()),
            $this->original(100, 200),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Png,
                mode: ImageVariantMode::Contain,
                scalePolicy: ImageScalePolicy::AllowUpscale,
            ),
        );

        self::assertEquals(new ImageDimensions(800, 450), $result->dimensions());
        $this->assertPixelColor($result->contents(), 400, 20, 255, 0, 0);
        $this->assertPixelColor($result->contents(), 400, 430, 0, 0, 255);
        $this->assertPixelAlpha($result->contents(), 0, 225, 127);
    }

    public function testContainUsesConfiguredJpegBackground(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->verticalBandsPng()),
            $this->original(100, 200),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Jpeg,
                mode: ImageVariantMode::Contain,
                background: ImageBackground::color('#123456'),
                scalePolicy: ImageScalePolicy::AllowUpscale,
            ),
        );

        $this->assertPixelNear($result->contents(), 0, 225, 18, 52, 86);
    }

    public function testContainUsesConfiguredColorBackgroundForPng(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->verticalBandsPng()),
            $this->original(100, 200),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Png,
                mode: ImageVariantMode::Contain,
                background: ImageBackground::color('#123456'),
            ),
        );

        $this->assertPixelColor($result->contents(), 0, 225, 18, 52, 86);
        self::assertSame(0, $this->pixelColor($result->contents(), 0, 225)['alpha']);
    }

    public function testContainDoesNotUpscaleASmallSourceByDefault(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->solidPng(100, 100, 255, 0, 0)),
            $this->original(100, 100),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Png,
                mode: ImageVariantMode::Contain,
            ),
        );

        $this->assertPixelAlpha($result->contents(), 349, 225, 127);
        $this->assertPixelColor($result->contents(), 350, 225, 255, 0, 0);
        $this->assertPixelAlpha($result->contents(), 450, 225, 127);
    }

    public function testContainUpscalesASmallSourceWhenAllowed(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->solidPng(100, 100, 255, 0, 0)),
            $this->original(100, 100),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Png,
                mode: ImageVariantMode::Contain,
                scalePolicy: ImageScalePolicy::AllowUpscale,
            ),
        );

        $this->assertPixelAlpha($result->contents(), 174, 225, 127);
        $this->assertPixelColor($result->contents(), 175, 225, 255, 0, 0);
        $this->assertPixelAlpha($result->contents(), 625, 225, 127);
    }

    public function testCenterCoverDoesNotUpscaleASmallSourceByDefault(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->solidPng(100, 100, 255, 0, 0)),
            $this->original(100, 100),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Png,
            ),
        );

        $this->assertPixelAlpha($result->contents(), 349, 225, 127);
        $this->assertPixelColor($result->contents(), 350, 225, 255, 0, 0);
        $this->assertPixelAlpha($result->contents(), 450, 225, 127);
    }

    public function testContainPreservesWebpAlphaWhenSupported(): void
    {
        if (!(new GdCapabilities())->supports(ImageFormat::Webp)) {
            self::markTestSkipped('GD WebP codec is not available.');
        }

        $result = $this->renderer()->render(
            ImageSource::fromFile($this->verticalBandsPng()),
            $this->original(100, 200),
            $this->definition(
                dimensions: new ImageDimensions(800, 450),
                format: ImageFormat::Webp,
                mode: ImageVariantMode::Contain,
                scalePolicy: ImageScalePolicy::AllowUpscale,
            ),
        );

        $this->assertPixelAlpha($result->contents(), 0, 225, 120);
    }

    public function testMissingSourceIsRejectedAsDecodeFailure(): void
    {
        $this->expectException(ImageDecodeException::class);
        $this->renderer()->render(
            ImageSource::fromFile($this->root . '/missing.jpg'),
            $this->original(1, 1),
            $this->definition(),
        );
    }

    public function testDimensionMismatchIsRejected(): void
    {
        $this->expectException(ImageValidationException::class);
        $this->renderer()->render(
            ImageSource::fromFile($this->jpeg(100, 50)),
            $this->original(100, 51),
            $this->definition(),
        );
    }

    public function testCorruptSourceIsRejectedAsDecodeFailure(): void
    {
        $path = $this->root . '/corrupt.png';
        file_put_contents($path, 'not an image');

        $this->expectException(ImageDecodeException::class);
        $this->renderer()->render(
            ImageSource::fromFile($path),
            $this->original(1, 1),
            $this->definition(),
        );
    }

    public function testOversizedPixelBudgetIsRejected(): void
    {
        $this->expectException(ImageValidationException::class);
        $this->renderer()->render(
            ImageSource::fromFile($this->jpeg(4097, 1)),
            $this->original(4097, 1),
            $this->definition(),
        );
    }

    public function testTransparentPngOutputPreservesAlpha(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->transparentPng()),
            $this->original(8, 8),
            $this->definition(format: ImageFormat::Png),
        );

        $this->assertPixelAlpha($result->contents(), 0, 0, 127);
    }

    public function testTransparentPngUsesWhiteBackgroundForJpeg(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->transparentPng()),
            $this->original(8, 8),
            $this->definition(),
        );

        $this->assertPixelNear($result->contents(), 0, 0, 255, 255, 255);
    }

    public function testTransparentPngUsesConfiguredJpegBackground(): void
    {
        $result = $this->renderer()->render(
            ImageSource::fromFile($this->transparentPng()),
            $this->original(8, 8),
            new ImageVariantDefinition(
                new ImageDimensions(64, 64),
                ImageFormat::Jpeg,
                ImageQuality::fromInt(100),
                ImageBackground::color('#123456'),
            ),
        );

        $this->assertPixelNear($result->contents(), 0, 0, 18, 52, 86);
    }

    private function renderer(): GdImageVariantRenderer
    {
        return new GdImageVariantRenderer(new GdCapabilities());
    }

    private function definition(
        ?ImageDimensions $dimensions = null,
        ImageFormat $format = ImageFormat::Jpeg,
        ImageVariantMode $mode = ImageVariantMode::CenterCover,
        ?ImageBackground $background = null,
        ImageScalePolicy $scalePolicy = ImageScalePolicy::DownscaleOnly,
    ): ImageVariantDefinition {
        if ($background === null) {
            if ($format === ImageFormat::Jpeg) {
                $background = ImageBackground::color('#ffffff');
            } else {
                $background = ImageBackground::transparent();
            }
        }

        return new ImageVariantDefinition(
            $dimensions ?? new ImageDimensions(64, 64),
            $format,
            ImageQuality::fromInt(90),
            $background,
            mode: $mode,
            scalePolicy: $scalePolicy,
        );
    }

    private function original(int $width, int $height): ImageOriginalReference
    {
        return new ImageOriginalReference(
            ImageOriginalStorage::Storage,
            'original.jpg',
            'v1',
            ImageFormat::Jpeg,
            new ImageDimensions($width, $height),
        );
    }

    private function jpeg(int $width, int $height): string
    {
        $path = $this->root . '/' . $width . '-' . $height . '.jpg';
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function solidPng(int $width, int $height, int $red, int $green, int $blue): string
    {
        $path = $this->root . '/' . $width . '-' . $height . '.png';
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        $color = imagecolorallocate(
            $image,
            min(255, max(0, $red)),
            min(255, max(0, $green)),
            min(255, max(0, $blue)),
        );
        self::assertNotFalse($color);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function verticalBandsPng(): string
    {
        $path = $this->root . '/vertical-bands.png';
        $image = imagecreatetruecolor(max(1, 100), max(1, 200));
        $red = imagecolorallocate($image, 255, 0, 0);
        $blue = imagecolorallocate($image, 0, 0, 255);
        self::assertNotFalse($red);
        self::assertNotFalse($blue);
        imagefilledrectangle($image, 0, 0, 99, 99, $red);
        imagefilledrectangle($image, 0, 100, 99, 199, $blue);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function transparentPng(): string
    {
        $path = $this->root . '/transparent.png';
        $image = imagecreatetruecolor(8, 8);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 0, 0, 127);
        self::assertNotFalse($transparent);
        imagefill($image, 0, 0, $transparent);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function assertPixelColor(
        string $contents,
        int $x,
        int $y,
        int $red,
        int $green,
        int $blue,
    ): void {
        $color = $this->pixelColor($contents, $x, $y);
        self::assertSame($red, $color['red']);
        self::assertSame($green, $color['green']);
        self::assertSame($blue, $color['blue']);
    }

    private function assertPixelNear(
        string $contents,
        int $x,
        int $y,
        int $red,
        int $green,
        int $blue,
    ): void {
        $color = $this->pixelColor($contents, $x, $y);
        self::assertEqualsWithDelta($red, $color['red'], 8);
        self::assertEqualsWithDelta($green, $color['green'], 8);
        self::assertEqualsWithDelta($blue, $color['blue'], 8);
    }

    private function assertPixelAlpha(string $contents, int $x, int $y, int $minimum): void
    {
        $image = imagecreatefromstring($contents);
        self::assertInstanceOf(GdImage::class, $image);
        self::assertGreaterThanOrEqual($minimum, (imagecolorat($image, $x, $y) >> 24) & 0x7f);
        imagedestroy($image);
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function pixelColor(string $contents, int $x, int $y): array
    {
        $image = imagecreatefromstring($contents);
        self::assertInstanceOf(GdImage::class, $image);
        $pixel = imagecolorat($image, $x, $y);
        self::assertNotFalse($pixel);
        $color = imagecolorsforindex($image, $pixel);
        imagedestroy($image);

        return $color;
    }
}
