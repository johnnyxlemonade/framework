<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

/**
 * Carries one source value before a higher-precedence layer replaces it
 */
final readonly class TranslationSourceContribution
{
    /**
     * Configures one resource-layer value and its provenance
     */
    public function __construct(
        public string $value,
        public TranslationSourceProvenance $provenance,
    ) {
    }
}
