<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Gd;

use GdImage;
use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Exception\ImageEncodeException;
use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;

/**
 * Encodes decoded images into a supported output format.
 * It validates codec availability, converts GD failures into typed image exceptions,
 * and composites transparency onto white for JPEG output.
 */
final class GdImageEncoder implements ImageEncoderInterface
{
    public function __construct(private readonly GdCapabilities $capabilities)
    {
    }

    public function encode(DecodedImage $image, ImageFormat $format, ImageQuality $quality): EncodedImage
    {
        $this->capabilities->assertEncoder($format);
        $resource = @imagecreatefromstring($image->normalizedPng());
        if (!$resource instanceof GdImage) {
            throw new ImageEncodeException('Normalized image data cannot be decoded for encoding.');
        }
        try {
            $output = $format === ImageFormat::Jpeg ? $this->jpegCanvas($resource, $image) : $resource;
            try {
                if (($format === ImageFormat::Png || $format === ImageFormat::Webp) && (@imagealphablending($output, false) !== true || @imagesavealpha($output, true) !== true)) {
                    throw new ImageEncodeException('Image alpha channel cannot be prepared for encoding.');
                }
                if (!ob_start()) {
                    throw new ImageEncodeException('Image output buffer cannot be started.');
                }
                $success = match ($format) {
                    ImageFormat::Jpeg => @imagejpeg($output, null, $quality->value),
                    ImageFormat::Png => @imagepng($output, null, (int) round((100 - $quality->value) * 9 / 100)),
                    ImageFormat::Webp => @imagewebp($output, null, $quality->value),
                };
                $contents = ob_get_clean();
                if ($success !== true || !is_string($contents) || $contents === '') {
                    throw new ImageEncodeException('Image encoding failed.');
                }
                return new EncodedImage($contents, $format, $image->dimensions());
            } finally {
                if ($output !== $resource) {
                    imagedestroy($output);
                }
            }
        } finally {
            imagedestroy($resource);
        }
    }

    private function jpegCanvas(GdImage $source, DecodedImage $image): GdImage
    {
        $dimensions = $image->dimensions();
        $canvas = @imagecreatetruecolor(max(1, $dimensions->width), max(1, $dimensions->height));
        if (!$canvas instanceof GdImage) {
            throw new ImageEncodeException('JPEG background canvas cannot be created.');
        }
        $white = @imagecolorallocate($canvas, 255, 255, 255);
        if (@imagealphablending($canvas, true) !== true || $white === false || !@imagefill($canvas, 0, 0, $white) || !@imagecopy($canvas, $source, 0, 0, 0, 0, $dimensions->width, $dimensions->height)) {
            imagedestroy($canvas);
            throw new ImageEncodeException('JPEG background cannot be composited.');
        }
        return $canvas;
    }
}
