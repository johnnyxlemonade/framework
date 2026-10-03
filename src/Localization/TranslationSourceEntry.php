<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

/**
 * Describes the effective source value for a locale, group and key identity
 */
final readonly class TranslationSourceEntry
{
    /**
     * @param list<TranslationSourceContribution> $contributions
     */
    public function __construct(
        public string $locale,
        public string $group,
        public string $key,
        public string $value,
        public array $contributions,
    ) {
    }
}
