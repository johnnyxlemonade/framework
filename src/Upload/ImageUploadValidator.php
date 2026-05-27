<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Psr\Http\Message\UploadedFileInterface;

final class ImageUploadValidator
{
    public function __construct(
        private readonly FileUploadValidator $fileValidator,
        private readonly TranslatorInterface $translator,
    ) {}

    public function validate(?UploadedFileInterface $file, ImageUploadOptions $options): void
    {
        $this->fileValidator->validate($file, new FileUploadOptions(
            targetDirectory: $options->targetDirectory(),
            targetRelativeDirectory: $options->targetRelativeDirectory(),
            maxBytes: $options->maxBytes(),
            allowedMimeTypes: $options->allowedMimeTypes(),
            allowedExtensions: $options->allowedExtensions(),
        ));

        /** @var UploadedFileInterface $file */
        $tmpPath = $this->fileValidator->resolvePath($file);

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            throw new UploadValidationException($this->translator->get('upload.image_not_valid'));
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
