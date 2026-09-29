<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Image\Contract\ImageUrlResolverInterface;
use Lemonade\Framework\Image\Exception\ImageCapabilityUnavailableException;
use Lemonade\Framework\Image\Exception\ImageDecodeException;
use Lemonade\Framework\Image\Exception\ImageEncodeException;
use Lemonade\Framework\Image\Exception\ImageValidationException;
use Lemonade\Framework\Image\Exception\ImageWriteException;
use Lemonade\Framework\Image\Exception\UnsupportedImageFormatException;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageEncoder;
use Lemonade\Framework\Image\Gd\GdImageProcessor;
use Lemonade\Framework\Image\FilesystemImageFileWriter;
use Lemonade\Framework\Image\ImageViewHelper;
use Lemonade\Framework\Image\ImageViewOptions;
use Lemonade\Framework\Image\Value\CropRectangle;
use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use PHPUnit\Framework\TestCase;

final class GdImageCapabilityTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD is not installed.');
        }
        $this->directory = sys_get_temp_dir() . '/lemonade-image-' . uniqid('', true);
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testDecodeResizeCropThumbnailAndEncodesJpegAndPng(): void
    {
        $processor = $this->processor();
        $decoded = $processor->decode(ImageSource::fromFile($this->jpeg(400, 200)));
        self::assertSame(100, $processor->resize($decoded, new ImageDimensions(100, 100))->dimensions()->width);
        self::assertSame(50, $processor->crop($decoded, new CropRectangle(0, 0, new ImageDimensions(50, 50)))->dimensions()->width);
        $thumbnail = $processor->thumbnail($decoded, new ImageDimensions(256, 256));
        self::assertEquals(new ImageDimensions(256, 256), $thumbnail->dimensions());
        self::assertNotSame('', $this->encoder()->encode($thumbnail, ImageFormat::Jpeg, ImageQuality::fromInt(85))->contents());
        self::assertNotSame('', $this->encoder()->encode($thumbnail, ImageFormat::Png, ImageQuality::fromInt(85))->contents());
    }

    public function testPngAlphaIsPreserved(): void
    {
        $encoded = $this->encoder()->encode($this->processor()->decode(ImageSource::fromFile($this->png())), ImageFormat::Png, ImageQuality::fromInt(85));
        $path = $this->directory . '/out.png';
        file_put_contents($path, $encoded->contents());
        $image = imagecreatefrompng($path);
        self::assertNotFalse($image);
        $index = imagecolorat($image, 0, 0);
        self::assertIsInt($index);
        $color = imagecolorsforindex($image, $index);
        self::assertSame(127, $color['alpha']);
        imagedestroy($image);
    }

    public function testTransparentPngIsCompositedOntoWhiteForJpeg(): void
    {
        $encoded = $this->encoder()->encode($this->processor()->decode(ImageSource::fromFile($this->png())), ImageFormat::Jpeg, ImageQuality::fromInt(100));
        $path = $this->directory . '/transparent.jpg';
        file_put_contents($path, $encoded->contents());
        $image = imagecreatefromjpeg($path);
        self::assertNotFalse($image);
        $index = imagecolorat($image, 0, 0);
        self::assertIsInt($index);
        $rgb = imagecolorsforindex($image, $index);
        self::assertGreaterThanOrEqual(250, $rgb['red']);
        self::assertGreaterThanOrEqual(250, $rgb['green']);
        self::assertGreaterThanOrEqual(250, $rgb['blue']);
        imagedestroy($image);
    }

    public function testWebpOrMissingCodecIsHandled(): void
    {
        $capabilities = new GdCapabilities();
        if (!$capabilities->supports(ImageFormat::Webp)) {
            $this->expectException(ImageCapabilityUnavailableException::class);
            $capabilities->assertDecoder(ImageFormat::Webp);
            return;
        }
        $source = imagecreatetruecolor(2, 2);
        $path = $this->directory . '/image.webp';
        imagewebp($source, $path, 85);
        imagedestroy($source);
        $decoded = $this->processor()->decode(ImageSource::fromFile($path));
        self::assertSame(ImageFormat::Webp, $decoded->sourceFormat());
        self::assertNotSame('', $this->encoder()->encode($decoded, ImageFormat::Webp, ImageQuality::fromInt(85))->contents());
    }

    public function testCorruptUnsupportedAndPixelBudgetAreRejected(): void
    {
        $corrupt = $this->directory . '/corrupt.jpg';
        file_put_contents($corrupt, 'not-an-image');
        try {
            $this->processor()->decode(ImageSource::fromFile($corrupt));
            self::fail('Corrupt image must fail decoding.');
        } catch (ImageDecodeException) {
            self::addToAssertionCount(1);
        }
        $gif = $this->directory . '/image.gif';
        $image = imagecreatetruecolor(20, 20);
        imagegif($image, $gif);
        imagedestroy($image);
        try {
            $this->processor()->decode(ImageSource::fromFile($gif));
            self::fail('GIF must not be accepted.');
        } catch (UnsupportedImageFormatException) {
            self::addToAssertionCount(1);
        }
        $this->expectException(ImageValidationException::class);
        (new GdImageProcessor(new GdCapabilities(), maxPixels: 10))->decode(ImageSource::fromFile($this->jpeg(20, 20)));
    }

    public function testInvalidCropQualityAndEncoderDataAreTyped(): void
    {
        $image = $this->processor()->decode(ImageSource::fromFile($this->jpeg(10, 10)));
        try {
            $this->processor()->crop($image, new CropRectangle(5, 5, new ImageDimensions(10, 10)));
            self::fail('Out-of-bounds crop must fail.');
        } catch (ImageValidationException) {
            self::addToAssertionCount(1);
        }
        try {
            ImageQuality::fromInt(101);
            self::fail('Invalid quality must fail.');
        } catch (ImageValidationException) {
            self::addToAssertionCount(1);
        }
        $this->expectException(ImageEncodeException::class);
        $this->encoder()->encode(new DecodedImage('invalid', new ImageDimensions(1, 1), ImageFormat::Png), ImageFormat::Jpeg, ImageQuality::fromInt(85));
    }

    public function testReferenceAndViewMarkupAreSafe(): void
    {
        $reference = new ImageReference('avatars/a.webp', new ImageDimensions(256, 256), ImageFormat::Webp);
        $helper = new ImageViewHelper(new class implements ImageUrlResolverInterface {
            public function url(ImageReference $reference): string { return 'https://example.test/uploads/' . $reference->publicPath() . '?x="'; }
        });
        $html = $helper->image($reference, new ImageViewOptions(alt: '<avatar>', class: 'x" onclick="bad'));
        self::assertStringContainsString('loading="lazy"', $html);
        self::assertStringContainsString('width="256" height="256"', $html);
        self::assertStringContainsString('&quot;', $html);
        self::assertSame('', $helper->image(null));
        $this->expectException(ImageValidationException::class);
        new ImageReference('../secret.jpg', new ImageDimensions(1, 1), ImageFormat::Jpeg);
    }

    public function testWriterMapsInvalidTargetToTypedException(): void
    {
        $directory = new DirectoryManager();
        $writer = new FilesystemImageFileWriter(new Filesystem($directory, new FileManager(), new LockManager($directory)));
        $this->expectException(ImageWriteException::class);
        $writer->write($this->encoder()->encode($this->processor()->decode(ImageSource::fromFile($this->jpeg(1, 1))), ImageFormat::Jpeg, ImageQuality::fromInt(85)), '');
    }

    private function processor(): GdImageProcessor { return new GdImageProcessor(new GdCapabilities()); }
    private function encoder(): GdImageEncoder { return new GdImageEncoder(new GdCapabilities()); }

    private function jpeg(int $width, int $height): string
    {
        $path = $this->directory . '/image-' . $width . 'x' . $height . '.jpg';
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagejpeg($image, $path, 90);
        imagedestroy($image);
        return $path;
    }

    private function png(): string
    {
        $path = $this->directory . '/transparent.png';
        $image = imagecreatetruecolor(2, 2);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        self::assertIsInt($transparent);
        imagefill($image, 0, 0, $transparent);
        imagepng($image, $path);
        imagedestroy($image);
        return $path;
    }
}
