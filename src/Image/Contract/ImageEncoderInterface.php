<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Contract;

use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;

/**
 * Encodes decoded images into a supported output format without choosing where
 * the resulting bytes are stored.
 */
interface ImageEncoderInterface
{
    public function encode(DecodedImage $image, ImageFormat $format, ImageQuality $quality): EncodedImage;
}
