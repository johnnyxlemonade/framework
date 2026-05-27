<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\Uploader\ConfiguredFileUploader;
use Lemonade\Framework\Upload\Uploader\ConfiguredImageUploader;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Psr\Http\Message\ServerRequestInterface;

final class UploadFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly UploadService $service,
        private readonly ServerRequestInterface $request,
        private readonly TranslatorInterface $translator,
        private readonly ApplicationContext $context,
    ) {}

    public function file(string $profile = 'default'): ConfiguredFileUploader
    {
        return $this->fileWithOptions($this->fileOptions($profile));
    }

    public function image(string $profile = 'default'): ConfiguredImageUploader
    {
        return $this->imageWithOptions($this->imageOptions($profile));
    }

    public function upload(string $inputName, string $profile = 'default'): UploadedFile
    {
        return $this->file($profile)->uploadFromRequest($this->request, $inputName);
    }

    public function uploadImage(string $inputName, string $profile = 'default'): UploadedImage
    {
        return $this->image($profile)->uploadFromRequest($this->request, $inputName);
    }

    public function fileWithOptions(FileUploadOptions $options): ConfiguredFileUploader
    {
        return new ConfiguredFileUploader($this->service, $options);
    }

    public function imageWithOptions(ImageUploadOptions $options): ConfiguredImageUploader
    {
        return new ConfiguredImageUploader($this->service, $options);
    }

    public function uploadWithOptions(string $inputName, FileUploadOptions $options): UploadedFile
    {
        return $this->fileWithOptions($options)->uploadFromRequest($this->request, $inputName);
    }

    public function uploadImageWithOptions(string $inputName, ImageUploadOptions $options): UploadedImage
    {
        return $this->imageWithOptions($options)->uploadFromRequest($this->request, $inputName);
    }

    public function fileOptions(string $profile = 'default'): FileUploadOptions
    {
        $profileData = $this->config->get("upload.files.profiles.{$profile}");
        if (!is_array($profileData)) {
            throw new UploadValidationException($this->translator->get('upload.file_profile_not_configured', ['profile' => $profile]));
        }

        $targetDirectory = $this->stringValue($profileData['target_directory'] ?? null);
        if ($targetDirectory === '') {
            throw new UploadValidationException($this->translator->get('upload.file_profile_missing_target_directory', ['profile' => $profile]));
        }

        return new FileUploadOptions(
            targetDirectory: $this->context->resolveUploadPath($targetDirectory),
            targetRelativeDirectory: $this->context->uploadRelativePath($targetDirectory),
            maxBytes: $this->intValue($profileData['max_bytes'] ?? null, 10_485_760),
            allowedMimeTypes: $this->stringList($profileData['allowed_mime_types'] ?? []),
            allowedExtensions: $this->stringList($profileData['allowed_extensions'] ?? []),
        );
    }

    public function imageOptions(string $profile = 'default'): ImageUploadOptions
    {
        $profileData = $this->config->get("upload.images.profiles.{$profile}");
        if (!is_array($profileData)) {
            throw new UploadValidationException($this->translator->get('upload.image_profile_not_configured', ['profile' => $profile]));
        }

        $targetDirectory = $this->stringValue($profileData['target_directory'] ?? null);
        if ($targetDirectory === '') {
            throw new UploadValidationException($this->translator->get('upload.image_profile_missing_target_directory', ['profile' => $profile]));
        }

        return new ImageUploadOptions(
            targetDirectory: $this->context->resolveUploadPath($targetDirectory),
            targetRelativeDirectory: $this->context->uploadRelativePath($targetDirectory),
            maxBytes: $this->intValue($profileData['max_bytes'] ?? null, 5_242_880),
            allowedMimeTypes: $this->stringList($profileData['allowed_mime_types'] ?? [
                'image/jpeg',
                'image/png',
                'image/webp',
            ]),
            allowedExtensions: $this->stringList($profileData['allowed_extensions'] ?? [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ]),
            reencode: $this->boolValue($profileData['reencode'] ?? null, true),
            minWidth: $this->nullableIntValue($profileData['min_width'] ?? null),
            maxWidth: $this->nullableIntValue($profileData['max_width'] ?? null),
            minHeight: $this->nullableIntValue($profileData['min_height'] ?? null),
            maxHeight: $this->nullableIntValue($profileData['max_height'] ?? null),
        );

    }

    private function stringValue(mixed $value, string $default = ''): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return $default;
    }

    private function intValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function nullableIntValue(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function boolValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $items[] = (string) $item;
            }
        }

        return $items;
    }
}
