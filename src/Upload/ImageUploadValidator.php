<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Adds image-content and dimension checks to generic upload validation.
 */
final readonly class ImageUploadValidator
{
    /**
     * Creates image validation on top of the shared generic upload boundary
     */
    public function __construct(
        private readonly FileUploadValidator $fileValidator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Validates an image upload and returns its server-detected MIME value
     */
    public function validate(
        ?UploadedFileInterface $file,
        ImageUploadOptions $options,
    ): MimeType {
        $fileOptions = new FileUploadOptions(
            targetDirectory: $options->targetDirectory(),
            targetRelativeDirectory: $options->targetRelativeDirectory(),
            maxBytes: $options->maxBytes(),
            allowedMimeTypes: $options->allowedMimeTypes(),
            allowedExtensions: $options->allowedExtensions(),
        );

        $mime = $this->fileValidator->validate($file, $fileOptions);

        /**
         * @var UploadedFileInterface $file
         */
        $tmpPath = $this->fileValidator->resolvePath($file);

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            throw new UploadValidationException($this->translator->get('upload.image_not_valid'));
        }

        $actualMime = $imageInfo['mime'];

        try {
            $format = ImageFormat::fromMimeType($actualMime);
        } catch (\InvalidArgumentException) {
            throw new UploadValidationException($this->translator->get('upload.image_mime_not_supported', [
                'mime' => $actualMime,
            ]));
        }

        if ($mime->value() !== $format->mimeType()) {
            throw new UploadValidationException($this->translator->get('upload.image_mime_not_supported', [
                'mime' => $mime->value(),
            ]));
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        $this->validateDimension(
            value: $width,
            min: $options->minWidth(),
            max: $options->maxWidth(),
            minMessageKey: 'upload.image_min_width',
            maxMessageKey: 'upload.image_max_width',
            parameterName: 'width',
        );

        $this->validateDimension(
            value: $height,
            min: $options->minHeight(),
            max: $options->maxHeight(),
            minMessageKey: 'upload.image_min_height',
            maxMessageKey: 'upload.image_max_height',
            parameterName: 'height',
        );

        return $mime;
    }

    private function validateDimension(
        int $value,
        ?int $min,
        ?int $max,
        string $minMessageKey,
        string $maxMessageKey,
        string $parameterName,
    ): void {
        if ($min !== null && $value < $min) {
            throw new UploadValidationException($this->translator->get($minMessageKey, [
                $parameterName => $min,
            ]));
        }

        if ($max !== null && $value > $max) {
            throw new UploadValidationException($this->translator->get($maxMessageKey, [
                $parameterName => $max,
            ]));
        }
    }
}
