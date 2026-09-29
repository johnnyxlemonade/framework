<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Gd;

use Lemonade\Framework\Image\Exception\ImageCapabilityUnavailableException;
use Lemonade\Framework\Image\Value\ImageFormat;

/**
 * Centralizes GD runtime initialization and codec availability checks.
 * It applies only the JPEG warning compatibility directive and never changes global warning reporting.
 */
final class GdCapabilities
{
    private bool $initialized = false;

    /**
     * Applies the one GD runtime compatibility setting that affects decoding.
     * It is best effort because the directive can be unavailable or immutable.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;
        if (extension_loaded('gd') && ini_get('gd.jpeg_ignore_warning') !== false) {
            ini_set('gd.jpeg_ignore_warning', '1');
        }
    }

    public function assertAvailable(): void
    {
        $this->initialize();
        if (!extension_loaded('gd')) {
            throw new ImageCapabilityUnavailableException('GD image processing is not available.');
        }
    }

    public function assertDecoder(ImageFormat $format): void
    {
        $this->assertAvailable();
        if (!function_exists($this->decoderFunction($format))) {
            throw new ImageCapabilityUnavailableException('The required GD image decoder is not available.');
        }
    }

    public function assertEncoder(ImageFormat $format): void
    {
        $this->assertAvailable();
        if (!function_exists($this->encoderFunction($format))) {
            throw new ImageCapabilityUnavailableException('The required GD image encoder is not available.');
        }
    }

    public function supports(ImageFormat $format): bool
    {
        return extension_loaded('gd')
            && function_exists($this->decoderFunction($format))
            && function_exists($this->encoderFunction($format));
    }

    private function decoderFunction(ImageFormat $format): string
    {
        return match ($format) {
            ImageFormat::Jpeg => 'imagecreatefromjpeg',
            ImageFormat::Png => 'imagecreatefrompng',
            ImageFormat::Webp => 'imagecreatefromwebp',
        };
    }

    private function encoderFunction(ImageFormat $format): string
    {
        return match ($format) {
            ImageFormat::Jpeg => 'imagejpeg',
            ImageFormat::Png => 'imagepng',
            ImageFormat::Webp => 'imagewebp',
        };
    }
}
