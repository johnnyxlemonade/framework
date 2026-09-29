<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Image\Exception\ImageDecodeException;
use Lemonade\Framework\Image\Exception\ImageValidationException;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageVariantRenderer;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use PHPUnit\Framework\TestCase;

final class GdImageVariantRendererTest extends TestCase
{
    private string $root = '';
    protected function setUp(): void { $this->root = sys_get_temp_dir() . '/lemonade-variant-' . uniqid('', true); mkdir($this->root, 0775, true); }
    protected function tearDown(): void { $files = glob($this->root . '/*'); foreach ($files === false ? [] : $files as $file) unlink($file); rmdir($this->root); }

    public function testCenterCoverProducesSquareForLandscapeAndPortrait(): void
    {
        foreach ([[200, 100], [100, 200]] as [$width, $height]) {
            $path = $this->jpeg($width, $height);
            $result = $this->renderer()->render(ImageSource::fromFile($path), $this->original($width, $height), $this->definition());
            self::assertEquals(new ImageDimensions(64, 64), $result->dimensions());
        }
    }

    public function testMissingCorruptAndDimensionMismatchAreTyped(): void
    {
        $this->expectException(ImageDecodeException::class);
        $this->renderer()->render(ImageSource::fromFile($this->root . '/missing.jpg'), $this->original(1, 1), $this->definition());
    }

    public function testDimensionMismatchIsRejected(): void
    {
        $path = $this->jpeg(100, 50);
        $this->expectException(ImageValidationException::class);
        $this->renderer()->render(ImageSource::fromFile($path), $this->original(100, 51), $this->definition());
    }

    public function testCorruptSourceIsRejectedAsDecodeFailure(): void
    {
        $path = $this->root . '/corrupt.png';
        file_put_contents($path, 'not an image');

        $this->expectException(ImageDecodeException::class);
        $this->renderer()->render(ImageSource::fromFile($path), $this->original(1, 1), $this->definition());
    }

    public function testOversizedPixelBudgetIsRejected(): void
    {
        $path = $this->jpeg(4097, 1);

        $this->expectException(ImageValidationException::class);
        $this->renderer()->render(ImageSource::fromFile($path), $this->original(4097, 1), $this->definition());
    }

    public function testTransparentPngOutputPreservesAlpha(): void
    {
        $result = $this->renderer()->render(ImageSource::fromFile($this->transparentPng()), $this->original(8, 8), $this->definition(ImageFormat::Png));
        $image = imagecreatefromstring($result->contents());

        self::assertNotFalse($image);
        self::assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7f);
        imagedestroy($image);
    }

    public function testTransparentPngUsesWhiteBackgroundForJpeg(): void
    {
        $result = $this->renderer()->render(ImageSource::fromFile($this->transparentPng()), $this->original(8, 8), $this->definition(ImageFormat::Jpeg));
        $this->assertPixelNear($result->contents(), 255, 255, 255);
    }

    public function testTransparentPngUsesConfiguredJpegBackground(): void
    {
        $definition = new ImageVariantDefinition(new ImageDimensions(64, 64), ImageFormat::Jpeg, ImageQuality::fromInt(100), '#123456');
        $result = $this->renderer()->render(ImageSource::fromFile($this->transparentPng()), $this->original(8, 8), $definition);
        $this->assertPixelNear($result->contents(), 18, 52, 86);
    }

    public function testTransparentPngOutputPreservesAlphaForWebpWhenSupported(): void
    {
        if (!(new GdCapabilities())->supports(ImageFormat::Webp)) {
            self::markTestSkipped('GD WebP codec is not available.');
        }

        $result = $this->renderer()->render(ImageSource::fromFile($this->transparentPng()), $this->original(8, 8), $this->definition(ImageFormat::Webp));
        $image = imagecreatefromstring($result->contents());

        self::assertNotFalse($image);
        self::assertGreaterThanOrEqual(120, (imagecolorat($image, 0, 0) >> 24) & 0x7f);
        imagedestroy($image);
    }

    private function renderer(): GdImageVariantRenderer { return new GdImageVariantRenderer(new GdCapabilities()); }
    private function definition(ImageFormat $format = ImageFormat::Jpeg): ImageVariantDefinition { return new ImageVariantDefinition(new ImageDimensions(64, 64), $format, ImageQuality::fromInt(90)); }
    private function original(int $width, int $height): ImageOriginalReference { return new ImageOriginalReference(ImageOriginalStorage::Storage, 'original.jpg', 'v1', ImageFormat::Jpeg, new ImageDimensions($width, $height)); }
    private function jpeg(int $width, int $height): string { $path = $this->root . '/' . $width . '-' . $height . '.jpg'; $image = imagecreatetruecolor(max(1, $width), max(1, $height)); imagejpeg($image, $path); imagedestroy($image); return $path; }
    private function transparentPng(): string
    {
        $path = $this->root . '/transparent.png';
        $image = imagecreatetruecolor(8, 8);
        imagealphablending($image, false); imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 0, 0, 127);
        self::assertNotFalse($transparent);
        imagefill($image, 0, 0, $transparent);
        imagepng($image, $path); imagedestroy($image);
        return $path;
    }
    private function assertPixelNear(string $contents, int $red, int $green, int $blue): void
    {
        $image = imagecreatefromstring($contents);
        self::assertNotFalse($image);
        $pixel = imagecolorat($image, 0, 0);
        self::assertNotFalse($pixel);
        $color = imagecolorsforindex($image, $pixel);
        self::assertEqualsWithDelta($red, $color['red'], 8); self::assertEqualsWithDelta($green, $color['green'], 8); self::assertEqualsWithDelta($blue, $color['blue'], 8);
        imagedestroy($image);
    }
}
