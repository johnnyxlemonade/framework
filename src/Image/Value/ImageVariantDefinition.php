<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Defines the complete immutable rendering contract for one derived image.
 *
 * Center cover fills the target by cropping when scaling permits it, while contain
 * keeps the full source visible. A smaller downscale-only source exposes this
 * definition's explicit background on the unused target canvas.
 */
final readonly class ImageVariantDefinition
{
    private ImageBackground $background;

    /**
     * Creates a rendering definition whose complete output contract contributes to cache identity.
     *
     * A missing background selects transparent canvas pixels. JPEG cannot encode that
     * policy and therefore requires an explicit color background.
     */
    public function __construct(
        private ImageDimensions $dimensions,
        private ImageFormat $format,
        private ImageQuality $quality,
        ?ImageBackground $background = null,
        private int $pipelineVersion = 1,
        private ImageVariantMode $mode = ImageVariantMode::CenterCover,
        private ImageScalePolicy $scalePolicy = ImageScalePolicy::DownscaleOnly,
    ) {
        $this->background = $background ?? ImageBackground::transparent();
        if (
            $pipelineVersion < 1
            || ($format === ImageFormat::Jpeg && $this->background->mode() === ImageBackgroundMode::Transparent)
        ) {
            throw new ImageValidationException('Image variant definition is invalid.');
        }
    }

    /**
     * Returns the exact canvas geometry produced by this variant.
     */
    public function dimensions(): ImageDimensions
    {
        return $this->dimensions;
    }

    /**
     * Returns the encoded format selected for the derived image.
     */
    public function format(): ImageFormat
    {
        return $this->format;
    }

    /**
     * Returns the encoder quality that contributes to this variant's identity.
     */
    public function quality(): ImageQuality
    {
        return $this->quality;
    }

    /**
     * Returns the geometry policy used to place the source on the target canvas.
     */
    public function mode(): ImageVariantMode
    {
        return $this->mode;
    }

    /**
     * Returns whether the geometry policy may enlarge smaller source images.
     */
    public function scalePolicy(): ImageScalePolicy
    {
        return $this->scalePolicy;
    }

    /**
     * Returns the explicit canvas policy used wherever the source leaves target pixels unused.
     */
    public function background(): ImageBackground
    {
        return $this->background;
    }

    /**
     * Returns the pipeline revision used to invalidate older variant identities.
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
            'mode' => $this->mode->value,
            'scale_policy' => $this->scalePolicy->value,
            'background' => $this->background->canonical(),
            'width' => $this->dimensions->width,
            'height' => $this->dimensions->height,
            'format' => $this->format->value,
            'quality' => $this->quality->value,
            'pipeline' => $this->pipelineVersion,
        ];
    }
}
