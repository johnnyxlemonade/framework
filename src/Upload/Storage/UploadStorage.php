<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Storage;

use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Psr\Http\Message\UploadedFileInterface;

final class UploadStorage
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Filesystem $filesystem,
    ) {}

    public function ensureTargetDirectory(string $targetDirectory): string
    {
        $targetDir = rtrim($targetDirectory, '/\\');

        if ($targetDir === '') {
            throw new UploadStorageException($this->translator->get('upload.target_directory_missing'));
        }

        try {
            $this->filesystem->create($targetDir, 0775);
        } catch (\Throwable) {
            throw new UploadStorageException($this->translator->get(
                'upload.create_target_directory_failed',
                ['directory' => $targetDir],
            ));
        }

        return $targetDir;
    }

    public function buildRelativePath(string $targetRelativeDirectory, string $storedFilename): string
    {
        return trim(str_replace(['\\', '/'], '/', $targetRelativeDirectory), '/')
            . '/'
            . ltrim(str_replace(['\\', '/'], '/', $storedFilename), '/');
    }

    public function generateFilename(?string $extension = null): string
    {
        $extension = $extension !== null ? strtolower(ltrim($extension, '.')) : '';

        try {
            $filename = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            throw new UploadStorageException($this->translator->get('upload.filename_generation_failed'));
        }

        return $filename . ($extension !== '' ? '.' . $extension : '');
    }

    public function buildPath(string $targetDirectory, string $storedFilename): string
    {
        return rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $storedFilename;
    }

    public function moveUploadedFile(UploadedFileInterface $file, string $storedPath): void
    {
        try {
            $file->moveTo($storedPath);
        } catch (\Throwable) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }
    }

    public function fileSize(string $path): int
    {
        try {
            $size = $this->filesystem->size($path);
        } catch (\Throwable) {
            throw new UploadStorageException($this->translator->get('upload.file_size_not_detected'));
        }

        return $size;
    }
}
