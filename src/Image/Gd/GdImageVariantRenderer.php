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
use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageBackgroundMode;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageScalePolicy;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Image\Value\ImageVariantMode;

/**
 * Produces a derived variant directly from a source file in one GD decode and resample pass.
 *
 * Center cover crops to fill when upscaling is allowed; contain preserves the full source.
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
     * Decodes, transforms, and encodes one variant while releasing GD resources before returning
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
            $target = $this->createTarget($definition);
            try {
                $this->transform(
                    $input,
                    $target,
                    $sourceDimensions,
                    $targetDimensions,
                    $definition->mode(),
                    $definition->scalePolicy(),
                );

                return $this->encode($target, $definition);
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($input);
        }
    }

    /**
     * Creates the single target canvas with the output format's explicit background policy.
     */
    private function createTarget(ImageVariantDefinition $definition): GdImage
    {
        $dimensions = $definition->dimensions();
        $target = @imagecreatetruecolor(
            max(1, $dimensions->width),
            max(1, $dimensions->height),
        );
        if (!$target instanceof GdImage) {
            throw new ImageTransformException('Image canvas cannot be created.');
        }

        $background = $definition->background();
        $prepared = match ($background->mode()) {
            ImageBackgroundMode::Transparent => $this->fillTransparentBackground($target),
            ImageBackgroundMode::Color => $this->fillColorBackground($target, $background),
        };
        if ($prepared) {
            return $target;
        }

        imagedestroy($target);
        throw new ImageTransformException('Image canvas background cannot be prepared.');
    }

    /**
     * Draws the source according to the definition's geometry policy.
     */
    private function transform(
        GdImage $source,
        GdImage $target,
        ImageDimensions $sourceDimensions,
        ImageDimensions $targetDimensions,
        ImageVariantMode $mode,
        ImageScalePolicy $scalePolicy,
    ): void {
        $transformed = match ($mode) {
            ImageVariantMode::CenterCover => $this->centerCover(
                $source,
                $target,
                $sourceDimensions,
                $targetDimensions,
                $scalePolicy,
            ),
            ImageVariantMode::Contain => $this->contain(
                $source,
                $target,
                $sourceDimensions,
                $targetDimensions,
                $scalePolicy,
            ),
        };
        if (!$transformed) {
            throw new ImageTransformException('Image variant cannot be transformed.');
        }
    }

    /**
     * Fills the target canvas by cropping equal excess area from opposite source edges.
     *
     * With downscale-only policy, a source that would need enlargement stays native-sized
     * and is centered on the already prepared target background.
     */
    private function centerCover(
        GdImage $source,
        GdImage $target,
        ImageDimensions $sourceDimensions,
        ImageDimensions $targetDimensions,
        ImageScalePolicy $scalePolicy,
    ): bool {
        $scale = max(
            $targetDimensions->width / $sourceDimensions->width,
            $targetDimensions->height / $sourceDimensions->height,
        );
        if ($scalePolicy === ImageScalePolicy::DownscaleOnly) {
            $scale = min(1, $scale);
        }
        $cropWidth = (int) floor($targetDimensions->width / $scale);
        $cropHeight = (int) floor($targetDimensions->height / $scale);
        $cropWidth = min($sourceDimensions->width, $cropWidth);
        $cropHeight = min($sourceDimensions->height, $cropHeight);
        $width = min($targetDimensions->width, max(1, (int) round($cropWidth * $scale)));
        $height = min($targetDimensions->height, max(1, (int) round($cropHeight * $scale)));

        return @imagecopyresampled(
            $target,
            $source,
            (int) floor(($targetDimensions->width - $width) / 2),
            (int) floor(($targetDimensions->height - $height) / 2),
            (int) floor(($sourceDimensions->width - $cropWidth) / 2),
            (int) floor(($sourceDimensions->height - $cropHeight) / 2),
            $width,
            $height,
            $cropWidth,
            $cropHeight,
        );
    }

    /**
     * Preserves the entire source within the target canvas while maintaining its aspect ratio.
     */
    private function contain(
        GdImage $source,
        GdImage $target,
        ImageDimensions $sourceDimensions,
        ImageDimensions $targetDimensions,
        ImageScalePolicy $scalePolicy,
    ): bool {
        $scale = min(
            $targetDimensions->width / $sourceDimensions->width,
            $targetDimensions->height / $sourceDimensions->height,
        );
        if ($scalePolicy === ImageScalePolicy::DownscaleOnly) {
            $scale = min(1, $scale);
        }
        $width = max(1, (int) round($sourceDimensions->width * $scale));
        $height = max(1, (int) round($sourceDimensions->height * $scale));

        return @imagecopyresampled(
            $target,
            $source,
            (int) floor(($targetDimensions->width - $width) / 2),
            (int) floor(($targetDimensions->height - $height) / 2),
            0,
            0,
            $width,
            $height,
            $sourceDimensions->width,
            $sourceDimensions->height,
        );
    }

    /**
     * Fills a target with an opaque configured color before source pixels are composited onto it.
     */
    private function fillColorBackground(GdImage $target, ImageBackground $background): bool
    {
        $hex = ltrim($background->colorValue() ?? '', '#');
        $color = @imagecolorallocate(
            $target,
            min(255, max(0, (int) hexdec(substr($hex, 0, 2)))),
            min(255, max(0, (int) hexdec(substr($hex, 2, 2)))),
            min(255, max(0, (int) hexdec(substr($hex, 4, 2)))),
        );

        return $color !== false
            && @imagealphablending($target, true) === true
            && @imagefill($target, 0, 0, $color);
    }

    /**
     * Fills a PNG or WebP target with transparent pixels and preserves alpha during encoding.
     */
    private function fillTransparentBackground(GdImage $target): bool
    {
        $transparent = @imagecolorallocatealpha($target, 0, 0, 0, 127);

        return @imagealphablending($target, false) === true
            && @imagesavealpha($target, true) === true
            && $transparent !== false
            && @imagefill($target, 0, 0, $transparent);
    }

    private function encode(GdImage $image, ImageVariantDefinition $definition): EncodedImage
    {
        if (!ob_start()) {
            throw new ImageEncodeException('Image output buffer cannot be started.');
        }
        $success = match ($definition->format()) {
            ImageFormat::Jpeg => @imagejpeg($image, null, $definition->quality()->value),
            ImageFormat::Png => @imagepng(
                $image,
                null,
                (int) round((100 - $definition->quality()->value) * 9 / 100),
            ),
            ImageFormat::Webp => @imagewebp($image, null, $definition->quality()->value),
        };
        $contents = ob_get_clean();
        if ($success !== true || !is_string($contents) || $contents === '') {
            throw new ImageEncodeException('Image encoding failed.');
        }

        return new EncodedImage($contents, $definition->format(), $definition->dimensions());
    }
}
