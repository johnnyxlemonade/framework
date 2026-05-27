<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Uploader;

use Lemonade\Framework\Upload\FileUploadOptions;
use Lemonade\Framework\Upload\Resolver\UploadedFileResolver;
use Lemonade\Framework\Upload\UploadService;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class ConfiguredFileUploader
{
    public function __construct(
        private readonly UploadService $service,
        private readonly FileUploadOptions $options,
        private readonly UploadedFileResolver $uploadedFileResolver = new UploadedFileResolver(),
    ) {}

    public function upload(?UploadedFileInterface $file): UploadedFile
    {
        return $this->service->uploadFile($file, $this->options);
    }

    public function uploadFromRequest(ServerRequestInterface $request, string $inputName): UploadedFile
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

    public function options(): FileUploadOptions
    {
        return $this->options;
    }

    /**
     * @return array{
     *     target_directory: string,
     *     max_bytes: int,
     *     allowed_mime_types: list<string>,
     *     allowed_extensions: list<string>
     * }
     */
    public function rules(): array
    {
        return [
            'target_directory' => $this->options->targetDirectory(),
            'max_bytes' => $this->options->maxBytes(),
            'allowed_mime_types' => $this->options->allowedMimeTypes(),
            'allowed_extensions' => $this->options->allowedExtensions(),
        ];
    }
}
