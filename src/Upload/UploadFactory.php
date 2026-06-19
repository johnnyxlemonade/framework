<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Config\FileUploadProfileConfig;
use Lemonade\Framework\Upload\Config\ImageUploadProfileConfig;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\Uploader\ConfiguredFileUploader;
use Lemonade\Framework\Upload\Uploader\ConfiguredImageUploader;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Psr\Http\Message\ServerRequestInterface;

final class UploadFactory
{
    public function __construct(
        private readonly UploadConfig $config,
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
        $profileData = $this->config->files[$profile] ?? null;
        if (!$profileData instanceof FileUploadProfileConfig) {
            throw new UploadValidationException($this->translator->get('upload.file_profile_not_configured', ['profile' => $profile]));
        }

        if ($profileData->targetDirectory === '') {
            throw new UploadValidationException($this->translator->get('upload.file_profile_missing_target_directory', ['profile' => $profile]));
        }

        return new FileUploadOptions(
            targetDirectory: $this->context->resolveUploadPath($profileData->targetDirectory),
            targetRelativeDirectory: $this->context->uploadRelativePath($profileData->targetDirectory),
            maxBytes: $profileData->maxBytes,
            allowedMimeTypes: $profileData->allowedMimeTypes,
            allowedExtensions: $profileData->allowedExtensions,
        );
    }

    public function imageOptions(string $profile = 'default'): ImageUploadOptions
    {
        $profileData = $this->config->images[$profile] ?? null;
        if (!$profileData instanceof ImageUploadProfileConfig) {
            throw new UploadValidationException($this->translator->get('upload.image_profile_not_configured', ['profile' => $profile]));
        }

        if ($profileData->targetDirectory === '') {
            throw new UploadValidationException($this->translator->get('upload.image_profile_missing_target_directory', ['profile' => $profile]));
        }

        return new ImageUploadOptions(
            targetDirectory: $this->context->resolveUploadPath($profileData->targetDirectory),
            targetRelativeDirectory: $this->context->uploadRelativePath($profileData->targetDirectory),
            maxBytes: $profileData->maxBytes,
            allowedMimeTypes: $profileData->allowedMimeTypes,
            allowedExtensions: $profileData->allowedExtensions,
            reencode: $profileData->reencode,
            minWidth: $profileData->minWidth,
            maxWidth: $profileData->maxWidth,
            minHeight: $profileData->minHeight,
            maxHeight: $profileData->maxHeight,
        );
    }
}
