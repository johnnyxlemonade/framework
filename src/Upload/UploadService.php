<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Exception\ImageException;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Upload\Exception\UploadImageProcessingException;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Coordinates validation, storage, and optional image processing for uploaded files.
 *
 * @internal Applications should select configured uploaders through UploadFactory.
 */
final readonly class UploadService
{
    /**
     * Creates the upload workflow from validation, storage, and image-processing boundaries.
     */
    public function __construct(
        private FileUploadValidator $fileValidator,
        private ImageUploadValidator $imageValidator,
        private UploadStorage $storage,
        private ImageEncoderInterface $imageEncoder,
        private UploadStaging $staging,
    ) {
    }

    /**
     * Validates and stores a generic uploaded file, optionally using an idempotent server-owned publication token.
     */
    public function uploadFile(
        ?UploadedFileInterface $file,
        FileUploadOptions $options,
        ?string $publicationToken = null,
        bool $allowExistingIdentical = false,
    ): UploadedFile {
        $staged = null;

        try {
            $staged = $this->staging->stage($file, $options->maxBytes());
            $mime = $this->fileValidator->validate($staged, $options);
            $this->staging->assertIntact($staged);

            $targetDir = $this->storage->ensureTargetDirectory(
                $options->targetDirectory(),
                $options->visibility(),
            );
            $extension = $this->clientExtension($staged);

            $storedFilename = $this->storage->generateFilename($extension, $publicationToken);
            $storedPath = $this->storage->buildPath($targetDir, $storedFilename);
            $storedRelativePath = $this->storage->buildRelativePath(
                $options->targetRelativeDirectory(),
                $storedFilename,
            );
            $size = $this->storage->publishSnapshot(
                $staged,
                $targetDir,
                $storedFilename,
                $options->visibility(),
                $allowExistingIdentical,
            );

            return new UploadedFile(
                storedFilename: $storedFilename,
                storedPath: $storedPath,
                storedRelativePath: $storedRelativePath,
                mimeType: $mime->value(),
                sizeBytes: $size,
                visibility: $options->visibility(),
                publicRelativePath: $options->visibility() === UploadVisibility::Public
                    ? $storedRelativePath
                    : null,
            );
        } finally {
            $this->staging->discard($staged);
        }
    }

    /**
     * Validates and stores an image, optionally re-encoding it and reusing identical token-owned output on retry.
     */
    public function uploadImage(
        ?UploadedFileInterface $file,
        ImageUploadOptions $options,
        ?string $publicationToken = null,
        bool $allowExistingIdentical = false,
    ): UploadedImage {
        $source = null;
        $publication = null;

        try {
            $source = $this->staging->stage($file, $options->maxBytes());
            $validated = $this->imageValidator->validate($source, $options);
            $mime = $validated->mimeType();
            $decoded = $validated->decodedImage();

            try {
                $format = ImageFormat::fromMimeType($mime->value());
            } catch (\InvalidArgumentException) {
                throw new UploadImageProcessingException('Image format is not supported.');
            }

            $extension = $format->extension();
            $publication = $source;
            $finalDecoded = $decoded;

            if ($options->reencode()) {
                try {
                    $encoded = $this->imageEncoder->encode($decoded, $format, ImageQuality::fromInt(85));
                } catch (ImageException $exception) {
                    throw new UploadImageProcessingException(
                        'Uploaded image cannot be processed.',
                        previous: $exception,
                    );
                }

                $publication = $this->staging->stageBytes(
                    $encoded->contents(),
                    'image.' . $extension,
                    $options->maxBytes(),
                );
                $finalDecoded = $this->imageValidator->validateGenerated(
                    $publication,
                    $format,
                    $decoded,
                );
            }

            $this->staging->assertIntact($publication);

            $targetDir = $this->storage->ensureTargetDirectory(
                $options->targetDirectory(),
                $options->visibility(),
            );
            $storedFilename = $this->storage->generateFilename($extension, $publicationToken);
            $storedPath = $this->storage->buildPath($targetDir, $storedFilename);
            $storedRelativePath = $this->storage->buildRelativePath(
                $options->targetRelativeDirectory(),
                $storedFilename,
            );
            $size = $this->storage->publishSnapshot(
                $publication,
                $targetDir,
                $storedFilename,
                $options->visibility(),
                $allowExistingIdentical,
            );

            $dimensions = $finalDecoded->dimensions();

            return new UploadedImage(
                storedFilename: $storedFilename,
                storedPath: $storedPath,
                storedRelativePath: $storedRelativePath,
                mimeType: $mime->value(),
                sizeBytes: $size,
                width: $dimensions->width,
                height: $dimensions->height,
                visibility: $options->visibility(),
                publicRelativePath: $options->visibility() === UploadVisibility::Public
                    ? $storedRelativePath
                    : null,
            );
        } finally {
            if ($publication !== $source) {
                $this->staging->discard($publication);
            }

            $this->staging->discard($source);
        }
    }

    private function clientExtension(StagedUpload $file): string
    {
        return strtolower(pathinfo($file->clientFilename(), PATHINFO_EXTENSION));
    }
}
