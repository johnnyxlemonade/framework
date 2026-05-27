<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Mime;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadProcessingException;

final class MimeTypeDetector
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function detect(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!is_string($mime) || $mime === '') {
            throw new UploadProcessingException($this->translator->get('upload.mime_not_detected'));
        }

        return strtolower($mime);
    }
}
