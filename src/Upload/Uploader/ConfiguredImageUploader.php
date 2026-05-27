<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Uploader;

use Lemonade\Framework\Upload\ImageUploadOptions;
use Lemonade\Framework\Upload\Resolver\UploadedFileResolver;
use Lemonade\Framework\Upload\UploadService;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class ConfiguredImageUploader
{
    public function __construct(
        private readonly UploadService $service,
        private readonly ImageUploadOptions $options,
        private readonly UploadedFileResolver $uploadedFileResolver = new UploadedFileResolver(),
    ) {}

    public function upload(?UploadedFileInterface $file): UploadedImage
    {
        return $this->service->uploadImage($file, $this->options);
    }

    public function uploadFromRequest(ServerRequestInterface $request, string $inputName): UploadedImage
    {
        $uploadedFiles = [];

        foreach ($request->getUploadedFiles() as $key => $value) {
            if (is_string($key)) {
                $uploadedFiles[$key] = $value;
            }
        }

        return $this->upload(
            $this->uploadedFileResolver->resolve($uploadedFiles, $inputName),
        );
    }

    public function options(): ImageUploadOptions
    {
        return $this->options;
    }

    /**
     * @return array{
     *     target_directory: string,
     *     max_bytes: int,
     *     allowed_mime_types: list<string>,
     *     allowed_extensions: list<string>,
     *     reencode: bool,
     *     min_width: int|null,
     *     max_width: int|null,
     *     min_height: int|null,
     *     max_height: int|null
     * }
     */
    public function rules(): array
    {
        return [
            'target_directory' => $this->options->targetDirectory(),
            'max_bytes' => $this->options->maxBytes(),
            'allowed_mime_types' => $this->options->allowedMimeTypes(),
            'allowed_extensions' => $this->options->allowedExtensions(),
            'reencode' => $this->options->reencode(),
            'min_width' => $this->options->minWidth(),
            'max_width' => $this->options->maxWidth(),
            'min_height' => $this->options->minHeight(),
            'max_height' => $this->options->maxHeight(),
        ];
    }
}
