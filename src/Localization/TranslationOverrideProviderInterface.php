<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

/**
 * Provides explicit mutable values over read-only source translations
 */
interface TranslationOverrideProviderInterface
{
    /**
     * Returns groups with explicit overrides for the selected locale
     *
     * @return list<string>
     */
    public function groups(string $locale): array;

    /**
     * Returns explicit override values for one exact locale and group
     *
     * @return array<string, string>
     */
    public function group(string $locale, string $group): array;
}
