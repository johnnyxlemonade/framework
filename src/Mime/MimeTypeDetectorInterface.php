<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

/**
 * Detects normalized MIME values from server-visible file content.
 */
interface MimeTypeDetectorInterface
{
    /**
     * Detects the normalized MIME value of a readable file
     */
    public function detect(string $path): MimeType;
}
