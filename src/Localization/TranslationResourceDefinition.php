<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

/**
 * Describes a registered translation resource root and its optional owner
 */
final readonly class TranslationResourceDefinition
{
    /**
     * Configures the canonical resource root path and optional source owner
     */
    public function __construct(
        public string $directory,
        public ?string $owner,
    ) {
    }
}
