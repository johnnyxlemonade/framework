<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Gd;

use GdImage;
use Lemonade\Framework\Image\Contract\ImageVariantRendererInterface;
use Lemonade\Framework\Image\Exception\ImageDecodeException;
use Lemonade\Framework\Image\Exception\ImageEncodeException;
use Lemonade\Framework\Image\Exception\ImageTransformException;
use Lemonade\Framework\Image\Exception\ImageValidationException;
use Lemonade\Framework\Image\Exception\UnsupportedImageFormatException;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;

/**
 * Produces a center-cover variant directly from a source file in one GD decode and resample pass.
 *
 * It keeps source and target resources private and releases both before returning encoded bytes.
 */
final readonly class GdImageVariantRenderer implements ImageVariantRendererInterface
{
    /**
     * Creates a renderer using the shared GD capability boundary
     */
    public function __construct(private readonly GdCapabilities $capabilities)
    {
    }

    /**
     * Decodes, center-crops, and encodes one variant while releasing GD resources before returning
     */
    public function render(
        ImageSource $source,
        ImageOriginalReference $original,
        ImageVariantDefinition $definition,
    ): EncodedImage {
        $path = $source->path();
        if (!is_file($path) || !is_readable($path)) {
            throw new ImageDecodeException('Image source is not readable.');
        }
        $size = @filesize($path);
        if (!is_int($size) || $size < 1 || $size > 20_971_520) {
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
        $this->capabilities->assertDecoder($format);
        $this->capabilities->assertEncoder($definition->format());
        $input = match ($format) {
            ImageFormat::Jpeg => @imagecreatefromjpeg($path),
            ImageFormat::Png => @imagecreatefrompng($path),
            ImageFormat::Webp => @imagecreatefromwebp($path),
        };
        if (!$input instanceof GdImage) {
            throw new ImageDecodeException('Image data cannot be decoded.');
        }
        try {
            $sourceDimensions = new ImageDimensions($info[0], $info[1]);
            $expected = $original->dimensions();
            if ($sourceDimensions->width !== $expected->width || $sourceDimensions->height !== $expected->height) {
                throw new ImageValidationException('Image source dimensions do not match its persistent reference.');
            }
            if (
                $sourceDimensions->width > 4096
                || $sourceDimensions->height > 4096
                || $sourceDimensions->pixels() > 10_000_000
            ) {
                throw new ImageValidationException('Image dimensions exceed allowed limits.');
            }
            $targetDimensions = $definition->dimensions();
            if (
                $targetDimensions->width > 4096
                || $targetDimensions->height > 4096
                || $targetDimensions->pixels() > 10_000_000
            ) {
                throw new ImageValidationException('Image dimensions exceed allowed limits.');
            }
            $scale = max($targetDimensions->width / $sourceDimensions->width, $targetDimensions->height / $sourceDimensions->height);
            $cropWidth = (int) floor($targetDimensions->width / $scale);
            $cropHeight = (int) floor($targetDimensions->height / $scale);
            $sourceX = (int) floor(($sourceDimensions->width - $cropWidth) / 2);
            $sourceY = (int) floor(($sourceDimensions->height - $cropHeight) / 2);
            $target = @imagecreatetruecolor(
                max(1, $targetDimensions->width),
                max(1, $targetDimensions->height),
            );
            if (!$target instanceof GdImage) {
                throw new ImageTransformException('Image canvas cannot be created.');
            }
            try {
                $transparent = @imagecolorallocatealpha($target, 0, 0, 0, 127);
                $transformed = @imagealphablending($target, false) === true
                    && @imagesavealpha($target, true) === true
                    && $transparent !== false
                    && @imagefill($target, 0, 0, $transparent)
                    && @imagecopyresampled(
                        $target,
                        $input,
                        0,
                        0,
                        $sourceX,
                        $sourceY,
                        $targetDimensions->width,
                        $targetDimensions->height,
                        $cropWidth,
                        $cropHeight,
                    );
                if (!$transformed) {
                    throw new ImageTransformException('Image variant cannot be transformed.');
                }
                return $this->encode($target, $definition);
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($input);
        }
    }

    private function encode(GdImage $image, ImageVariantDefinition $definition): EncodedImage
    {
        $output = $image;
        if ($definition->format() === ImageFormat::Jpeg) {
            $output = @imagecreatetruecolor(
                max(1, $definition->dimensions()->width),
                max(1, $definition->dimensions()->height),
            );
            if (!$output instanceof GdImage) {
                throw new ImageEncodeException('JPEG background canvas cannot be created.');
            }
            $hex = ltrim($definition->jpegBackground(), '#');
            $background = @imagecolorallocate(
                $output,
                min(255, max(0, (int) hexdec(substr($hex, 0, 2)))),
                min(255, max(0, (int) hexdec(substr($hex, 2, 2)))),
                min(255, max(0, (int) hexdec(substr($hex, 4, 2)))),
            );
            $composited = $background !== false
                && @imagealphablending($output, true) === true
                && @imagefill($output, 0, 0, $background)
                && @imagecopy(
                    $output,
                    $image,
                    0,
                    0,
                    0,
                    0,
                    $definition->dimensions()->width,
                    $definition->dimensions()->height,
                );
            if (!$composited) {
                imagedestroy($output);
                throw new ImageEncodeException('JPEG background cannot be composited.');
            }
        }
        try {
            if (!ob_start()) {
                throw new ImageEncodeException('Image output buffer cannot be started.');
            }
            $success = match ($definition->format()) {
                ImageFormat::Jpeg => @imagejpeg($output, null, $definition->quality()->value),
                ImageFormat::Png => @imagepng(
                    $output,
                    null,
                    (int) round((100 - $definition->quality()->value) * 9 / 100),
                ),
                ImageFormat::Webp => @imagewebp($output, null, $definition->quality()->value),
            };
            $contents = ob_get_clean();
            if ($success !== true || !is_string($contents) || $contents === '') {
                throw new ImageEncodeException('Image encoding failed.');
            }
            return new EncodedImage($contents, $definition->format(), $definition->dimensions());
        } finally {
            if ($output !== $image) {
                imagedestroy($output);
            }
        }
    }
}
