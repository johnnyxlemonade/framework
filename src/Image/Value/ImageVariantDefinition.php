<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Defines the complete normalized center-cover rendering contract for one derived image
 */
final readonly class ImageVariantDefinition
{
    /**
     * Creates a normalized rendering definition with validated JPEG background and pipeline version
     */
    public function __construct(
        private ImageDimensions $dimensions,
        private ImageFormat $format,
        private ImageQuality $quality,
        private string $jpegBackground = '#ffffff',
        private int $pipelineVersion = 1,
    ) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $jpegBackground) !== 1 || $pipelineVersion < 1) {
            throw new ImageValidationException('Image variant definition is invalid.');
        }
    }

    /**
     * Returns the target geometry enforced by variant rendering
     */
    public function dimensions(): ImageDimensions
    {
        return $this->dimensions;
    }

    /**
     * Returns the output format selected for the derived variant
     */
    public function format(): ImageFormat
    {
        return $this->format;
    }

    /**
     * Returns the encoder quality included in the variant identity
     */
    public function quality(): ImageQuality
    {
        return $this->quality;
    }

    /**
     * Returns the normalized opaque background used when encoding JPEG output
     */
    public function jpegBackground(): string
    {
        return strtolower($this->jpegBackground);
    }

    /**
     * Returns the pipeline revision that invalidates prior variant identities
     */
    public function pipelineVersion(): int
    {
        return $this->pipelineVersion;
    }

    /**
     * Returns the normalized output-affecting values used to derive a stable cache key.
     *
     * @return array<string, int|string>
     */
    public function canonical(): array
    {
        return [
            'operation' => 'center-cover-thumbnail',
            'width' => $this->dimensions->width,
            'height' => $this->dimensions->height,
            'format' => $this->format->value,
            'quality' => $this->quality->value,
            'jpeg_background' => $this->jpegBackground(),
            'pipeline' => $this->pipelineVersion,
        ];
    }
}
