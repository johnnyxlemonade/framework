<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Contract;

use Lemonade\Framework\Image\Value\CropRectangle;
use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ResizeMode;

/**
 * Transforms decoded bitmap images while keeping the concrete image backend
 * hidden from application code.
 */
interface ImageProcessorInterface
{
    public function decode(ImageSource $source): DecodedImage;
    public function resize(DecodedImage $image, ImageDimensions $dimensions, ResizeMode $mode = ResizeMode::Contain): DecodedImage;
    public function crop(DecodedImage $image, CropRectangle $rectangle): DecodedImage;
    public function thumbnail(DecodedImage $image, ImageDimensions $dimensions): DecodedImage;
}
