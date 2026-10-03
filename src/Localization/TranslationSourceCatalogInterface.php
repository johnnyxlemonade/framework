<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

/**
 * Exposes physical source translations without the mutable override layer
 */
interface TranslationSourceCatalogInterface
{
    /**
     * Returns locales with at least one physical source file
     *
     * @return list<string>
     */
    public function locales(): array;

    /**
     * Returns groups physically covered by the selected locale
     *
     * @return list<string>
     */
    public function groups(string $locale): array;

    /**
     * Returns effective source values and all contributions without locale fallback
     *
     * @return list<TranslationSourceEntry>
     */
    public function entries(?string $locale = null): array;

    /**
     * Returns effective source values for one group and exact locale
     *
     * @return array<string, string>
     */
    public function lines(string $locale, string $group): array;
}
