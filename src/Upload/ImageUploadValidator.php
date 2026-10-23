<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Image\Contract\ImageProcessorInterface;
use Lemonade\Framework\Image\Exception\ImageException;
use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\ValueObject\ValidatedImageUpload;

/**
 * Adds image decoding, detected-image MIME agreement, and dimension checks to the generic upload boundary.
 *
 * @internal UploadService supplies only sealed private snapshots and publishes only after this validator succeeds.
 */
final readonly class ImageUploadValidator
{
    /**
     * Extends the generic extension and MIME boundary with image-specific content validation.
     */
    public function __construct(
        private FileUploadValidator $fileValidator,
        private TranslatorInterface $translator,
        private ImageProcessorInterface $imageProcessor,
    ) {
    }

    /**
     * Validates an image snapshot and returns its detected MIME together with completely decoded pixels.
     *
     * The detected MIME must agree with both MimeTypeCatalog and decoded image metadata.
     *
     * @throws UploadValidationException When generic upload, image-content, MIME, or dimension validation fails
     */
    public function validate(
        StagedUpload $file,
        ImageUploadOptions $options,
    ): ValidatedImageUpload {
        $fileOptions = new FileUploadOptions(
            targetDirectory: $options->targetDirectory(),
            targetRelativeDirectory: $options->targetRelativeDirectory(),
            maxBytes: $options->maxBytes(),
            allowedExtensions: $options->allowedExtensions(),
            visibility: $options->visibility(),
            allowedRisks: $options->allowedRisks(),
        );

        $mime = $this->fileValidator->validate($file, $fileOptions);

        try {
            $decoded = $this->imageProcessor->decode(
                ImageSource::fromFile($file->path()),
            );
        } catch (ImageException $exception) {
            throw new UploadValidationException(
                $this->translator->get('upload.image_not_valid'),
                previous: $exception,
            );
        }

        if ($mime->value() !== $decoded->sourceFormat()->mimeType()) {
            throw new UploadValidationException($this->translator->get('upload.image_mime_not_supported', [
                'mime' => $mime->value(),
            ]));
        }

        $dimensions = $decoded->dimensions();

        $this->validateDimension(
            value: $dimensions->width,
            min: $options->minWidth(),
            max: $options->maxWidth(),
            minMessageKey: 'upload.image_min_width',
            maxMessageKey: 'upload.image_max_width',
            parameterName: 'width',
        );

        $this->validateDimension(
            value: $dimensions->height,
            min: $options->minHeight(),
            max: $options->maxHeight(),
            minMessageKey: 'upload.image_min_height',
            maxMessageKey: 'upload.image_max_height',
            parameterName: 'height',
        );

        return new ValidatedImageUpload($mime, $decoded);
    }

    /**
     * Fully decodes framework-generated output and verifies that encoding preserved its format and dimensions.
     *
     * @throws UploadValidationException When generated output cannot be completely decoded or changed identity
     */
    public function validateGenerated(
        StagedUpload $file,
        ImageFormat $expectedFormat,
        DecodedImage $source,
    ): DecodedImage {
        try {
            $decoded = $this->imageProcessor->decode(
                ImageSource::fromFile($file->path()),
            );
        } catch (ImageException $exception) {
            throw new UploadValidationException(
                $this->translator->get('upload.image_not_valid'),
                previous: $exception,
            );
        }

        $decodedDimensions = $decoded->dimensions();
        $sourceDimensions = $source->dimensions();

        if (
            $decoded->sourceFormat() !== $expectedFormat
            || $decodedDimensions->width !== $sourceDimensions->width
            || $decodedDimensions->height !== $sourceDimensions->height
        ) {
            throw new UploadValidationException($this->translator->get('upload.image_not_valid'));
        }

        return $decoded;
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
