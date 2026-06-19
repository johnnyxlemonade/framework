<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class UploadConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'upload';
    }

    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function fileProfile(
        string $profile,
        string $targetDirectory,
        int $maxBytes,
        array $allowedMimeTypes,
        array $allowedExtensions = [],
    ): self {
        return $this
            ->set("files.{$profile}.target_directory", $targetDirectory)
            ->set("files.{$profile}.max_bytes", $maxBytes)
            ->set("files.{$profile}.allowed_mime_types", array_values($allowedMimeTypes))
            ->set("files.{$profile}.allowed_extensions", array_values($allowedExtensions));
    }

    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function imageProfile(
        string $profile,
        string $targetDirectory,
        int $maxBytes,
        array $allowedMimeTypes,
        array $allowedExtensions = [],
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
