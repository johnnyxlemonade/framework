<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Defines the pixels used where a source image does not cover its variant canvas.
 *
 * A transparent background deliberately has no color value. A color background
 * always exposes one normalized opaque RGB value.
 */
final readonly class ImageBackground
{
    /**
     * Creates a validated background from factory-controlled mode and color values.
     */
    private function __construct(
        private ImageBackgroundMode $mode,
        private ?string $color,
    ) {
    }

    /**
     * Creates a background whose unused canvas pixels retain alpha transparency.
     */
    public static function transparent(): self
    {
        return new self(ImageBackgroundMode::Transparent, null);
    }

    /**
     * Creates an opaque RGB background from an exact hexadecimal color value.
     *
     * @throws ImageValidationException When the color is not an exact #RRGGBB value
     */
    public static function color(string $color): self
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new ImageValidationException('Image background color is invalid.');
        }

        return new self(ImageBackgroundMode::Color, strtolower($color));
    }

    /**
     * Returns the policy that determines how unused canvas pixels are initialized.
     */
    public function mode(): ImageBackgroundMode
    {
        return $this->mode;
    }

    /**
     * Returns the normalized color for color mode, or null when the background is transparent.
     */
    public function colorValue(): ?string
    {
        return $this->color;
    }

    /**
     * Returns the stable output-affecting representation used by variant cache identity.
     */
    public function canonical(): string
    {
        return $this->color ?? $this->mode->value;
    }
}
