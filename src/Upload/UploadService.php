<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Upload\Image\GdImageProcessor;
use Lemonade\Framework\Upload\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Psr\Http\Message\UploadedFileInterface;

final class UploadService
{
    public function __construct(
        private readonly FileUploadValidator $fileValidator,
        private readonly ImageUploadValidator $imageValidator,
        private readonly UploadStorage $storage,
        private readonly MimeTypeDetector $mimeTypeDetector,
        private readonly GdImageProcessor $imageProcessor,
    ) {}

    public function uploadFile(?UploadedFileInterface $file, FileUploadOptions $options): UploadedFile
    {
        $this->fileValidator->validate($file, $options);

        /** @var UploadedFileInterface $file */
        $tmpPath = $this->fileValidator->resolvePath($file);

        $targetDir = $this->storage->ensureTargetDirectory($options->targetDirectory());
        $mime = $this->mimeTypeDetector->detect($tmpPath);
        $extension = $this->clientExtension($file);

        $storedFilename = $this->storage->generateFilename($extension);
        $storedPath = $this->storage->buildPath($targetDir, $storedFilename);
        $storedRelativePath = $this->storage->buildRelativePath(
            $options->targetRelativeDirectory(),
            $storedFilename,
        );

        $this->storage->moveUploadedFile($file, $storedPath);

        return new UploadedFile(
            storedFilename: $storedFilename,
            storedPath: $storedPath,
            storedRelativePath: $storedRelativePath,
            mimeType: $mime,
            sizeBytes: $this->storage->fileSize($storedPath),
        );
    }

    public function uploadImage(?UploadedFileInterface $file, ImageUploadOptions $options): UploadedImage
    {
        $this->imageValidator->validate($file, $options);

        /** @var UploadedFileInterface $file */
        $tmpPath = $this->fileValidator->resolvePath($file);

        $targetDir = $this->storage->ensureTargetDirectory($options->targetDirectory());
        $mime = $this->mimeTypeDetector->detect($tmpPath);
        $extension = $this->imageProcessor->extensionFromMime($mime);

        $storedFilename = $this->storage->generateFilename($extension);
        $storedPath = $this->storage->buildPath($targetDir, $storedFilename);
        $storedRelativePath = $this->storage->buildRelativePath(
            $options->targetRelativeDirectory(),
            $storedFilename,
        );

        if ($options->reencode()) {
            $this->imageProcessor->reencode($tmpPath, $storedPath, $mime);
        } else {
            $this->storage->moveUploadedFile($file, $storedPath);
        }

        $dimensions = $this->imageProcessor->dimensions($storedPath);

        return new UploadedImage(
            storedFilename: $storedFilename,
            storedPath: $storedPath,
            storedRelativePath: $storedRelativePath,
            mimeType: $this->mimeTypeDetector->detect($storedPath),
            sizeBytes: $this->storage->fileSize($storedPath),
            width: $dimensions['width'],
            height: $dimensions['height'],
        );
    }

    private function clientExtension(UploadedFileInterface $file): string
    {
        return strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
    }
}
