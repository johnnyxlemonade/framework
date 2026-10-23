<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDetectorInterface;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\Staging\StagedUpload;

/**
 * Validates generic uploads against transport, byte limits, explicit extension policy, and detected content.
 *
 * A client MIME declaration is never trusted: the temporary payload is detected server-side and must match its
 * filename extension through MimeTypeCatalog.
 *
 * @internal UploadService supplies only sealed private snapshots created by UploadStaging.
 */
final readonly class FileUploadValidator
{
    /**
     * Combines translated validation failures with server-side MIME detection and catalog compatibility rules.
     */
    public function __construct(
        private TranslatorInterface $translator,
        private MimeTypeDetectorInterface $detector,
        private MimeTypeCatalog $catalog,
        private UploadRiskPolicy $riskPolicy = new UploadRiskPolicy(),
    ) {
    }

    /**
     * Accepts a payload only after its transport state, size, explicit extension, and detected MIME family agree.
     *
     * The returned MIME is detected from temporary server-visible bytes, never the client declaration.
     *
     * @throws UploadValidationException When the snapshot size, extension, MIME family, or risk policy is invalid
     */
    public function validate(
        StagedUpload $file,
        FileUploadOptions $options,
    ): MimeType {
        $size = $file->sizeBytes();

        if ($size <= 0) {
            throw new UploadValidationException($this->translator->get('upload.file_empty'));
        }

        if ($size > $options->maxBytes()) {
            throw new UploadValidationException($this->translator->get('upload.file_too_large'));
        }

        $mime = $this->detector->detect($file->path());

        $extension = $this->clientExtension($file);
        $allowedExtensions = $this->normalizeExtensions($options->allowedExtensions());

        if ($allowedExtensions === [] || $extension === '' || !in_array($extension, $allowedExtensions, true)) {
            throw new UploadValidationException($this->translator->get(
                'upload.extension_not_allowed',
                ['extension' => $extension],
            ));
        }

        if (!$this->catalog->matches($extension, $mime)) {
            throw new UploadValidationException($this->translator->get(
                'upload.mime_not_allowed',
                ['mime' => $mime->value()],
            ));
        }

        $definition = $this->catalog->definitionForExtension($extension);

        if (
            $definition === null
            || !$this->riskPolicy->allows(
                $definition->risk(),
                $options->visibility(),
                $options->allowedRisks(),
            )
        ) {
            throw new UploadValidationException($this->translator->get(
                'upload.mime_not_allowed',
                ['mime' => $mime->value()],
            ));
        }

        return $mime;
    }

    private function clientExtension(StagedUpload $file): string
    {
        return strtolower(pathinfo($file->clientFilename(), PATHINFO_EXTENSION));
    }

    /**
     * @param list<string>|array<int|string, string> $extensions
     * @return list<string>
     */
    private function normalizeExtensions(array $extensions): array
    {
        return array_values(array_unique(array_map(
            static fn(string $extension): string => strtolower(ltrim(trim($extension), '.')),
            $extensions,
        )));
    }
}
