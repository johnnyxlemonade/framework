<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Gd;

use GdImage;
use Lemonade\Framework\Image\Contract\ImageProcessorInterface;
use Lemonade\Framework\Image\Exception\ImageDecodeException;
use Lemonade\Framework\Image\Exception\ImageTransformException;
use Lemonade\Framework\Image\Exception\ImageValidationException;
use Lemonade\Framework\Image\Exception\UnsupportedImageFormatException;
use Lemonade\Framework\Image\Value\CropRectangle;
use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ResizeMode;

/**
 * Decodes supported bitmap sources and produces transformed image values
 * without exposing the underlying GD resource to callers.
 *
 * Transformations return new image values and never mutate the input image.
 * Conservative dimension and pixel limits bound the memory retained by GD canvases.
 */
final class GdImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private readonly GdCapabilities $capabilities,
        private readonly int $maxWidth = 4096,
        private readonly int $maxHeight = 4096,
        private readonly int $maxPixels = 10_000_000,
        private readonly int $maxInputBytes = 20_971_520,
    ) {
    }

    public function decode(ImageSource $source): DecodedImage
    {
        $path = $source->path();
        if (!is_file($path) || !is_readable($path)) {
            throw new ImageDecodeException('Image source is not readable.');
        }
        $size = filesize($path);
        if (!is_int($size) || $size < 1 || $size > $this->maxInputBytes) {
            throw new ImageValidationException('Image input size is outside allowed limits.');
        }
        $info = @getimagesize($path);
        if (!is_array($info)) {
            throw new ImageDecodeException('Image metadata cannot be read.');
        }
        try {
            $format = ImageFormat::fromMimeType($info['mime']);
        } catch (\InvalidArgumentException) {
            throw new UnsupportedImageFormatException('The image format is not supported.');
        }
        $dimensions = new ImageDimensions($info[0], $info[1]);
        $this->assertDimensionsAllowed($dimensions);
        $this->capabilities->assertDecoder($format);
        $resource = match ($format) {
            ImageFormat::Jpeg => @imagecreatefromjpeg($path),
            ImageFormat::Png => @imagecreatefrompng($path),
            ImageFormat::Webp => @imagecreatefromwebp($path),
        };
        if (!$resource instanceof GdImage) {
            throw new ImageDecodeException('Image data cannot be decoded.');
        }
        try {
            return $this->decoded($resource, $dimensions, $format);
        } finally {
            imagedestroy($resource);
        }
    }

    public function resize(DecodedImage $image, ImageDimensions $dimensions, ResizeMode $mode = ResizeMode::Contain): DecodedImage
    {
        $this->assertDimensionsAllowed($dimensions);
        $source = $this->resource($image);
        try {
            $sourceDimensions = $image->dimensions();
            if ($mode === ResizeMode::Stretch) {
                return $this->resample($source, $sourceDimensions, $dimensions, $image->sourceFormat());
            }
            $scale = min($dimensions->width / $sourceDimensions->width, $dimensions->height / $sourceDimensions->height);
            $target = new ImageDimensions(max(1, (int) round($sourceDimensions->width * $scale)), max(1, (int) round($sourceDimensions->height * $scale)));
            return $this->resample($source, $sourceDimensions, $target, $image->sourceFormat());
        } finally {
            imagedestroy($source);
        }
    }

    public function crop(DecodedImage $image, CropRectangle $rectangle): DecodedImage
    {
        $sourceDimensions = $image->dimensions();
        if ($rectangle->x + $rectangle->dimensions->width > $sourceDimensions->width || $rectangle->y + $rectangle->dimensions->height > $sourceDimensions->height) {
            throw new ImageValidationException('Crop rectangle exceeds image bounds.');
        }
        $source = $this->resource($image);
        try {
            $target = $this->canvas($rectangle->dimensions);
            try {
                if (!@imagecopy($target, $source, 0, 0, $rectangle->x, $rectangle->y, $rectangle->dimensions->width, $rectangle->dimensions->height)) {
                    throw new ImageTransformException('Image crop failed.');
                }
                return $this->decoded($target, $rectangle->dimensions, $image->sourceFormat());
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }

    public function thumbnail(DecodedImage $image, ImageDimensions $dimensions): DecodedImage
    {
        $this->assertDimensionsAllowed($dimensions);
        $sourceDimensions = $image->dimensions();
        $scale = max($dimensions->width / $sourceDimensions->width, $dimensions->height / $sourceDimensions->height);
        $crop = new ImageDimensions(max(1, (int) floor($dimensions->width / $scale)), max(1, (int) floor($dimensions->height / $scale)));
        $rectangle = new CropRectangle(
            (int) floor(($sourceDimensions->width - $crop->width) / 2),
            (int) floor(($sourceDimensions->height - $crop->height) / 2),
            $crop,
        );
        return $this->resize($this->crop($image, $rectangle), $dimensions, ResizeMode::Stretch);
    }

    private function assertDimensionsAllowed(ImageDimensions $dimensions): void
    {
        if ($dimensions->width > $this->maxWidth || $dimensions->height > $this->maxHeight || $dimensions->pixels() > $this->maxPixels) {
            throw new ImageValidationException('Image dimensions exceed allowed limits.');
        }
    }

    private function resource(DecodedImage $image): GdImage
    {
        $this->capabilities->assertDecoder(ImageFormat::Png);
        $resource = @imagecreatefromstring($image->normalizedPng());
        if (!$resource instanceof GdImage) {
            throw new ImageTransformException('Normalized image data cannot be decoded.');
        }
        return $resource;
    }

    private function canvas(ImageDimensions $dimensions): GdImage
    {
        $canvas = @imagecreatetruecolor(max(1, $dimensions->width), max(1, $dimensions->height));
        if (!$canvas instanceof GdImage) {
            throw new ImageTransformException('Image canvas cannot be created.');
        }
        $blending = @imagealphablending($canvas, false);
        $savedAlpha = @imagesavealpha($canvas, true);
        $transparent = @imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        if ($blending !== true || $savedAlpha !== true || $transparent === false || !@imagefill($canvas, 0, 0, $transparent)) {
            imagedestroy($canvas);
            throw new ImageTransformException('Transparent image canvas cannot be initialized.');
        }
        return $canvas;
    }

    private function resample(GdImage $source, ImageDimensions $sourceDimensions, ImageDimensions $targetDimensions, ImageFormat $sourceFormat): DecodedImage
    {
        $target = $this->canvas($targetDimensions);
        try {
            if (!@imagecopyresampled($target, $source, 0, 0, 0, 0, $targetDimensions->width, $targetDimensions->height, $sourceDimensions->width, $sourceDimensions->height)) {
                throw new ImageTransformException('Image resize failed.');
            }
            return $this->decoded($target, $targetDimensions, $sourceFormat);
        } finally {
            imagedestroy($target);
        }
    }

    private function decoded(GdImage $resource, ImageDimensions $dimensions, ImageFormat $sourceFormat): DecodedImage
    {
        $this->capabilities->assertEncoder(ImageFormat::Png);
        if (@imagealphablending($resource, false) !== true || @imagesavealpha($resource, true) !== true || !ob_start()) {
            throw new ImageTransformException('Normalized image data cannot be prepared.');
        }
        $encoded = @imagepng($resource, null, 6);
        $contents = ob_get_clean();
        if ($encoded !== true || !is_string($contents)) {
            throw new ImageTransformException('Normalized image data cannot be encoded.');
        }
        return new DecodedImage($contents, $dimensions, $sourceFormat);
    }
}
