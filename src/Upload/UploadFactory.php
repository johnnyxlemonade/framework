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

/**
 * Exposes request-scoped uploaders whose named profiles are resolved against the current application context.
 *
 * Profile failures are translated into upload validation errors before storage is attempted.
 */
final class UploadFactory
{
    /**
     * Binds resolved profile configuration to the request and public upload-path context.
     */
    public function __construct(
        private readonly UploadConfig $config,
        private readonly UploadService $service,
        private readonly ServerRequestInterface $request,
        private readonly TranslatorInterface $translator,
        private readonly ApplicationContext $context,
    ) {
    }

    /**
     * Creates a generic uploader for a configured profile without reading request input yet.
     *
     * @throws UploadValidationException When the requested profile is absent or lacks a target directory
     */
    public function file(string $profile = 'default'): ConfiguredFileUploader
    {
        return $this->fileWithOptions($this->fileOptions($profile));
    }

    /**
     * Creates an image uploader for a configured profile without reading request input yet.
     *
     * @throws UploadValidationException When the requested profile is absent or lacks a target directory
     */
    public function image(string $profile = 'default'): ConfiguredImageUploader
    {
        return $this->imageWithOptions($this->imageOptions($profile));
    }

    /**
     * Resolves one request upload by input name and stores it through the selected generic profile.
     *
     * @throws UploadValidationException When the profile or submitted payload violates the upload policy
     */
    public function upload(string $inputName, string $profile = 'default'): UploadedFile
    {
        return $this->file($profile)->uploadFromRequest($this->request, $inputName);
    }

    /**
     * Resolves one request upload by input name and stores it through the selected image profile.
     *
     * @throws UploadValidationException When the profile or submitted image violates the upload policy
     */
    public function uploadImage(string $inputName, string $profile = 'default'): UploadedImage
    {
        return $this->image($profile)->uploadFromRequest($this->request, $inputName);
    }

    /**
     * Wraps an already-resolved generic policy in an uploader without consulting named configuration.
     */
    public function fileWithOptions(FileUploadOptions $options): ConfiguredFileUploader
    {
        return new ConfiguredFileUploader($this->service, $options);
    }

    /**
     * Wraps an already-resolved image policy in an uploader without consulting named configuration.
     */
    public function imageWithOptions(ImageUploadOptions $options): ConfiguredImageUploader
    {
        return new ConfiguredImageUploader($this->service, $options);
    }

    /**
     * Resolves and stores one request upload using a caller-supplied generic policy.
     *
     * @throws UploadValidationException When the submitted payload violates the supplied policy
     */
    public function uploadWithOptions(string $inputName, FileUploadOptions $options): UploadedFile
    {
        return $this->fileWithOptions($options)->uploadFromRequest($this->request, $inputName);
    }

    /**
     * Resolves and stores one request upload using a caller-supplied image policy.
     *
     * @throws UploadValidationException When the submitted image violates the supplied policy
     */
    public function uploadImageWithOptions(string $inputName, ImageUploadOptions $options): UploadedImage
    {
        return $this->imageWithOptions($options)->uploadFromRequest($this->request, $inputName);
    }

    /**
     * Resolves a named generic profile into absolute and public-relative storage paths.
     *
     * @throws UploadValidationException When the profile is absent or has no target directory
     */
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
            targetDirectory: $this->context->uploadPath($profileData->targetDirectory),
            targetRelativeDirectory: $this->context->uploadRelativePath($profileData->targetDirectory),
            maxBytes: $profileData->maxBytes,
            allowedExtensions: $profileData->allowedExtensions,
        );
    }

    /**
     * Resolves a named image profile into absolute and public-relative storage paths.
     *
     * @throws UploadValidationException When the profile is absent or has no target directory
     */
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
            targetDirectory: $this->context->uploadPath($profileData->targetDirectory),
            targetRelativeDirectory: $this->context->uploadRelativePath($profileData->targetDirectory),
            maxBytes: $profileData->maxBytes,
            allowedExtensions: $profileData->allowedExtensions,
            reencode: $profileData->reencode,
            minWidth: $profileData->minWidth,
            maxWidth: $profileData->maxWidth,
            minHeight: $profileData->minHeight,
            maxHeight: $profileData->maxHeight,
        );
    }
}
