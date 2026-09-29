<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Contract;

use Lemonade\Framework\Image\Value\EncodedImage;

/**
 * Persists already encoded image bytes at a caller-selected path.
 *
 * Implementations do not own naming, public URLs, or retention policy.
 */
interface ImageFileWriterInterface
{
    public function write(EncodedImage $image, string $targetPath): void;
}
