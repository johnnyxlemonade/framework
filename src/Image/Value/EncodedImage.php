<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Carries encoded image bytes with the format and dimensions produced by an encoder
 */
final readonly class EncodedImage
{
    /**
     * Creates encoded image bytes paired with the format and dimensions they represent
     */
    public function __construct(
        private string $contents,
        private ImageFormat $format,
        private ImageDimensions $dimensions,
    ) {
    }

    /**
     * Returns the complete encoded payload ready for persistence
     */
    public function contents(): string
    {
        return $this->contents;
    }

    /**
     * Returns the format used to encode the payload
     */
    public function format(): ImageFormat
    {
        return $this->format;
    }

    /**
     * Returns the output dimensions represented by the encoded payload
     */
    public function dimensions(): ImageDimensions
    {
        return $this->dimensions;
    }
}
