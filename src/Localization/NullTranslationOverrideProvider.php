<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

/**
 * Keeps source-only resolution until an application supplies a mutable override source
 */
final readonly class NullTranslationOverrideProvider implements TranslationOverrideProviderInterface
{
    /**
     * Returns no groups with explicit override values
     *
     * @return list<string>
     */
    public function groups(string $locale): array
    {
        unset($locale);

        return [];
    }

    /**
     * Returns no explicit override values
     *
     * @return array<string, string>
     */
    public function group(string $locale, string $group): array
    {
        unset($locale, $group);

        return [];
    }
}
