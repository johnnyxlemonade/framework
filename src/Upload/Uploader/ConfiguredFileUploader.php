<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Uploader;

use Lemonade\Framework\Upload\FileUploadOptions;
use Lemonade\Framework\Upload\Resolver\UploadedFileResolver;
use Lemonade\Framework\Upload\UploadService;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Executes generic uploads against one immutable resolved policy.
 */
final class ConfiguredFileUploader
{
    /**
     * Couples the upload workflow to one policy without owning request state.
     */
    public function __construct(
        private readonly UploadService $service,
        private readonly FileUploadOptions $options,
        private readonly UploadedFileResolver $uploadedFileResolver = new UploadedFileResolver(),
    ) {
    }

    /**
     * Validates and publishes one supplied upload using this uploader's policy.
     */
    public function upload(?UploadedFileInterface $file): UploadedFile
    {
        return $this->service->uploadFile($file, $this->options);
    }

    /**
     * Resolves a nested request input and validates and publishes its uploaded file.
     */
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

    /**
     * Exposes the immutable policy used by subsequent upload operations.
     */
    public function options(): FileUploadOptions
    {
        return $this->options;
    }

    /**
     * Exports server-enforced rules for consumers that need to describe this uploader without changing validation.
     *
     * @return array{
     *     target_directory: string,
     *     max_bytes: int,
     *     allowed_extensions: list<string>
     * }
     */
    public function rules(): array
    {
        return [
            'target_directory' => $this->options->targetDirectory(),
            'max_bytes' => $this->options->maxBytes(),
            'allowed_extensions' => $this->options->allowedExtensions(),
        ];
    }
}
