<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Upload\UploadVisibility;

/**
 * Defines immutable source values for named generic-file and image upload profiles.
 */
final class UploadConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an empty definition that can declare named file and image upload profiles.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Identifies definitions consumed by the upload configuration resolver.
     */
    public static function moduleKey(): string
    {
        return 'upload';
    }

    /**
     * Declares a generic-file profile whose explicit extensions are validated against MimeTypeCatalog at runtime.
     *
     * @param list<string> $allowedExtensions Canonical application allowlist; detected MIME must match each filename suffix
     * @param list<MimeRisk> $allowedRisks Non-passive categories explicitly authorized for private storage
     */
    public function fileProfile(
        string $profile,
        string $targetDirectory,
        int $maxBytes,
        array $allowedExtensions = [],
        UploadVisibility $visibility = UploadVisibility::Public,
        array $allowedRisks = [],
    ): self {
        return $this
            ->set("files.{$profile}.target_directory", $targetDirectory)
            ->set("files.{$profile}.max_bytes", $maxBytes)
            ->set("files.{$profile}.allowed_extensions", array_values($allowedExtensions))
            ->set("files.{$profile}.visibility", $visibility->value)
            ->set("files.{$profile}.allowed_risks", array_map(
                static fn(MimeRisk $risk): string => $risk->value,
                array_values($allowedRisks),
            ));
    }

    /**
     * Declares an image profile whose explicit extensions gate catalog-backed MIME and image-content validation.
     *
     * @param list<string> $allowedExtensions Canonical application allowlist; detected MIME must match each filename suffix
     * @param list<MimeRisk> $allowedRisks Non-passive categories explicitly authorized for private storage
     */
    public function imageProfile(
        string $profile,
        string $targetDirectory,
        int $maxBytes,
        array $allowedExtensions = [],
        bool $reencode = true,
        ?int $minWidth = null,
        ?int $maxWidth = null,
        ?int $minHeight = null,
        ?int $maxHeight = null,
        UploadVisibility $visibility = UploadVisibility::Public,
        array $allowedRisks = [],
    ): self {
        $this
            ->set("images.{$profile}.target_directory", $targetDirectory)
            ->set("images.{$profile}.max_bytes", $maxBytes)
            ->set("images.{$profile}.allowed_extensions", array_values($allowedExtensions))
            ->set("images.{$profile}.reencode", $reencode)
            ->set("images.{$profile}.visibility", $visibility->value)
            ->set("images.{$profile}.allowed_risks", array_map(
                static fn(MimeRisk $risk): string => $risk->value,
                array_values($allowedRisks),
            ));

        if ($minWidth !== null) {
            $this->set("images.{$profile}.min_width", $minWidth);
        }

        if ($maxWidth !== null) {
            $this->set("images.{$profile}.max_width", $maxWidth);
        }

        if ($minHeight !== null) {
            $this->set("images.{$profile}.min_height", $minHeight);
        }

        if ($maxHeight !== null) {
            $this->set("images.{$profile}.max_height", $maxHeight);
        }

        return $this;
    }
}
