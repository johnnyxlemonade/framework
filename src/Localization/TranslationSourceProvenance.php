<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

/**
 * Describes the layer and resource root that provide a source value
 */
final readonly class TranslationSourceProvenance
{
    /**
     * Configures the source kind, root and optional declared owner
     */
    public function __construct(
        public string $kind,
        public string $directory,
        public ?string $owner = null,
    ) {
    }
}
