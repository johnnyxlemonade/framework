<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Contract\ImageFileWriterInterface;
use Lemonade\Framework\Image\Contract\ImageProcessorInterface;
use Lemonade\Framework\Image\Exception\ImageException;
use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Upload\Exception\UploadImageProcessingException;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Coordinates validation, storage, and optional image processing for uploaded files.
 */
final readonly class UploadService
{
    /**
     * Creates the upload workflow from validation, storage, and image-processing boundaries
     */
    public function __construct(
        private readonly FileUploadValidator $fileValidator,
        private readonly ImageUploadValidator $imageValidator,
        private readonly UploadStorage $storage,
        private readonly ImageProcessorInterface $imageProcessor,
        private readonly ImageEncoderInterface $imageEncoder,
        private readonly ImageFileWriterInterface $imageWriter,
    ) {
    }

    /**
     * Validates and stores a generic uploaded file
     */
    public function uploadFile(
        ?UploadedFileInterface $file,
        FileUploadOptions $options,
    ): UploadedFile {
        $mime = $this->fileValidator->validate($file, $options);

        /**
         * @var UploadedFileInterface $file
         */
        $tmpPath = $this->fileValidator->resolvePath($file);

        $targetDir = $this->storage->ensureTargetDirectory($options->targetDirectory());
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
            mimeType: $mime->value(),
            sizeBytes: $this->storage->fileSize($storedPath),
        );
    }

    /**
     * Validates and stores an image, optionally re-encoding it before publication
     */
    public function uploadImage(
        ?UploadedFileInterface $file,
        ImageUploadOptions $options,
    ): UploadedImage {
        $mime = $this->imageValidator->validate($file, $options);

        /**
         * @var UploadedFileInterface $file
         */
        $tmpPath = $this->fileValidator->resolvePath($file);

        $targetDir = $this->storage->ensureTargetDirectory($options->targetDirectory());

        try {
            $format = ImageFormat::fromMimeType($mime->value());
        } catch (\InvalidArgumentException) {
            throw new UploadImageProcessingException('Image format is not supported.');
        }

        $extension = $format->extension();

        $storedFilename = $this->storage->generateFilename($extension);
        $storedPath = $this->storage->buildPath($targetDir, $storedFilename);
        $storedRelativePath = $this->storage->buildRelativePath(
            $options->targetRelativeDirectory(),
            $storedFilename,
        );

        if ($options->reencode()) {
            try {
                $decoded = $this->decodeImage($tmpPath);
                $encoded = $this->imageEncoder->encode($decoded, $format, ImageQuality::fromInt(85));

                $this->imageWriter->write($encoded, $storedPath);
            } catch (ImageException $exception) {
                throw new UploadImageProcessingException('Uploaded image cannot be processed.', previous: $exception);
            }
        } else {
            $this->storage->moveUploadedFile($file, $storedPath);
        }

        $dimensions = $this->decodeImage($storedPath)->dimensions();

        return new UploadedImage(
            storedFilename: $storedFilename,
            storedPath: $storedPath,
            storedRelativePath: $storedRelativePath,
            mimeType: $mime->value(),
            sizeBytes: $this->storage->fileSize($storedPath),
            width: $dimensions->width,
            height: $dimensions->height,
        );
    }

    private function clientExtension(UploadedFileInterface $file): string
    {
        return strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
    }

    private function decodeImage(string $path): DecodedImage
    {
        try {
            return $this->imageProcessor->decode(ImageSource::fromFile($path));
        } catch (ImageException $exception) {
            throw new UploadImageProcessingException('Uploaded image cannot be processed.', previous: $exception);
        }
    }
}
