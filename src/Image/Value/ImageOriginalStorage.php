<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Selects the storage boundary containing a persistent original; it does not imply a public URL
 */
enum ImageOriginalStorage
{
    case Upload;
    case Storage;
}
