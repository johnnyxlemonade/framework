<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Defines immutable source values for named generic-file and image upload profiles.
 */
final class UploadConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an upload configuration definition for framework upload profiles
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Provides the configuration namespace consumed by the upload subsystem
     */
    public static function moduleKey(): string
    {
        return 'upload';
    }

    /**
     * Defines one generic-file profile whose extension policy is always required for acceptance
     *
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimeTypes
     */
    public function fileProfile(
        string $profile,
        string $targetDirectory,
        int $maxBytes,
        array $allowedExtensions = [],
        array $allowedMimeTypes = [],
    ): self {
        return $this
            ->set("files.{$profile}.target_directory", $targetDirectory)
            ->set("files.{$profile}.max_bytes", $maxBytes)
            ->set("files.{$profile}.allowed_mime_types", array_values($allowedMimeTypes))
            ->set("files.{$profile}.allowed_extensions", array_values($allowedExtensions));
    }

    /**
     * Defines one image profile with optional MIME narrowing after extension and byte validation
     *
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimeTypes
     */
    public function imageProfile(
        string $profile,
        string $targetDirectory,
        int $maxBytes,
        array $allowedExtensions = [],
        array $allowedMimeTypes = [],
        bool $reencode = true,
        ?int $minWidth = null,
        ?int $maxWidth = null,
        ?int $minHeight = null,
        ?int $maxHeight = null,
    ): self {
        $this
            ->set("images.{$profile}.target_directory", $targetDirectory)
            ->set("images.{$profile}.max_bytes", $maxBytes)
            ->set("images.{$profile}.allowed_mime_types", array_values($allowedMimeTypes))
            ->set("images.{$profile}.allowed_extensions", array_values($allowedExtensions))
            ->set("images.{$profile}.reencode", $reencode);

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
