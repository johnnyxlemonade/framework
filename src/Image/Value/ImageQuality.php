<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/** Carries an explicitly validated encoder quality in the inclusive 0–100 range. */
final readonly class ImageQuality
{
    private function __construct(public int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0 || $value > 100) {
            throw new ImageValidationException('Image quality must be between 0 and 100.');
        }

        return new self($value);
    }
}
