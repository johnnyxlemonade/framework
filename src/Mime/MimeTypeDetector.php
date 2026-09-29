<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

use RuntimeException;

/**
 * Detects a file's media type from server-visible content rather than client metadata.
 */
final readonly class MimeTypeDetector implements MimeTypeDetectorInterface
{
    /**
     * Detects one normalized media type through the fileinfo extension
     */
    public function detect(string $path): MimeType
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!is_string($mime) || $mime === '') {
            throw new RuntimeException('MIME type cannot be detected.');
        }

        return MimeType::fromString($mime);
    }
}
