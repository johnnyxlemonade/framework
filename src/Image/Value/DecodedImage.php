<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Holds a normalized immutable bitmap value while keeping the mutable GD resource
 * outside the public image API.
 */
final readonly class DecodedImage
{
    /** @internal The normalized PNG payload is an implementation boundary, not a GD resource. */
    public function __construct(
        private string $normalizedPng,
        private ImageDimensions $dimensions,
        private ImageFormat $sourceFormat,
    ) {
    }

    public function dimensions(): ImageDimensions
    {
        return $this->dimensions;
    }

    public function sourceFormat(): ImageFormat
    {
        return $this->sourceFormat;
    }

    /** @internal */
    public function normalizedPng(): string
    {
        return $this->normalizedPng;
    }
}
