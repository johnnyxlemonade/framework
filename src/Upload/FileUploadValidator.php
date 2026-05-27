<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Psr\Http\Message\UploadedFileInterface;

final class FileUploadValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function validate(?UploadedFileInterface $file, FileUploadOptions $options): void
    {
        if ($file === null) {
            throw new UploadValidationException($this->translator->get('upload.payload_missing'));
        }

        $error = $file->getError();
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadValidationException($this->uploadErrorMessage($error));
        }

        $tmpPath = $this->resolvePath($file);

        $size = $file->getSize() ?? 0;
        if ($size <= 0) {
            throw new UploadValidationException($this->translator->get('upload.file_empty'));
        }

        if ($size > $options->maxBytes()) {
            throw new UploadValidationException($this->translator->get('upload.file_too_large'));
        }

        $mime = $this->detectMimeType($tmpPath);

        $allowedMimeTypes = $this->normalizeMimeTypes($options->allowedMimeTypes());
        if ($allowedMimeTypes !== [] && !in_array($mime, $allowedMimeTypes, true)) {
            throw new UploadValidationException($this->translator->get('upload.mime_not_allowed', ['mime' => $mime]));
        }

        $extension = $this->clientExtension($file);
        $allowedExtensions = $this->normalizeExtensions($options->allowedExtensions());

        if ($allowedExtensions !== [] && ($extension === '' || !in_array($extension, $allowedExtensions, true))) {
            throw new UploadValidationException($this->translator->get('upload.extension_not_allowed', ['extension' => $extension]));
        }
    }

    public function resolvePath(UploadedFileInterface $file): string
    {
        $stream = $file->getStream();
        $meta = $stream->getMetadata();
        $uri = is_array($meta) ? ($meta['uri'] ?? null) : null;

        if (!is_string($uri) || $uri === '' || !is_file($uri)) {
            throw new UploadValidationException($this->translator->get('upload.tmp_not_valid'));
        }

        return $uri;
    }

    private function detectMimeType(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!is_string($mime) || $mime === '') {
            throw new UploadValidationException($this->translator->get('upload.mime_not_detected'));
        }

        return strtolower($mime);
    }

    private function clientExtension(UploadedFileInterface $file): string
    {
        return strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
    }

    /**
     * @param list<string>|array<int|string, string> $mimeTypes
     * @return list<string>
     */
    private function normalizeMimeTypes(array $mimeTypes): array
    {
        return array_values(array_unique(array_map(
            static fn(string $mimeType): string => strtolower(trim($mimeType)),
            $mimeTypes,
        )));
    }

    /**
     * @param list<string>|array<int|string, string> $extensions
     * @return list<string>
     */
    private function normalizeExtensions(array $extensions): array
    {
        return array_values(array_unique(array_map(
            static fn(string $extension): string => strtolower(ltrim(trim($extension), '.')),
            $extensions,
        )));
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $this->translator->get('upload.error_too_large'),
            UPLOAD_ERR_PARTIAL => $this->translator->get('upload.error_partial'),
            UPLOAD_ERR_NO_FILE => $this->translator->get('upload.error_no_file'),
            UPLOAD_ERR_NO_TMP_DIR => $this->translator->get('upload.error_no_tmp_dir'),
            UPLOAD_ERR_CANT_WRITE => $this->translator->get('upload.error_cant_write'),
            UPLOAD_ERR_EXTENSION => $this->translator->get('upload.error_stopped_by_extension'),
            default => $this->translator->get('upload.error_unknown'),
        };
    }
}
