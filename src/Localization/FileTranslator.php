<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Localization\Config\LocalizationConfig;

/**
 * Resolves source catalogs and runtime overrides with the configured locale fallback
 */
final class FileTranslator implements TranslatorInterface
{
    private ?string $localeOverride = null;

    private readonly TranslationSourceCatalogInterface $sources;

    private readonly TranslationOverrideProviderInterface $overrides;

    /**
     * Configures locale settings, source resources and the optional mutable override provider
     */
    public function __construct(
        ApplicationContext $context,
        private readonly LocalizationConfig $config,
        ?TranslationResourceRegistry $resources = null,
        ?TranslationSourceCatalogInterface $sources = null,
        ?TranslationOverrideProviderInterface $overrides = null,
    ) {
        $this->sources = $sources ?? new FileTranslationSourceCatalog($context, $resources);
        $this->overrides = $overrides ?? new NullTranslationOverrideProvider();
    }

    public function setLocale(?string $locale): self
    {
        $value = $locale !== null ? trim($locale) : '';
        $this->localeOverride = $value !== '' ? $value : null;

        return $this;
    }

    public function locale(): ?string
    {
        return $this->localeOverride;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        [$group, $item] = $this->splitKey($key);
        $locale = $this->resolveLocale($locale);
        $fallbackLocale = $this->fallbackLocale();

        $line = $this->resolvedLines($group, $locale)[$item]
            ?? $this->resolvedLines($group, $fallbackLocale)[$item]
            ?? $key;

        if ($replacements === []) {
            return $line;
        }

        $search = [];
        $replace = [];
        foreach ($replacements as $name => $value) {
            $search[] = '{' . $name . '}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $line);
    }

    public function group(string $group, ?string $locale = null): array
    {
        $resolvedLocale = $this->resolveLocale($locale);
        $primary = $this->resolvedLines($group, $resolvedLocale);
        $fallback = $this->resolvedLines($group, $this->fallbackLocale());

        return array_replace($fallback, $primary);
    }

    public function all(?string $locale = null): array
    {
        $resolvedLocale = $this->resolveLocale($locale);
        $output = [];

        foreach ($this->groupNames($resolvedLocale) as $group) {
            $output[$group] = $this->group($group, $resolvedLocale);
        }

        return $output;
    }

    /**
     * Splits a translator key into its group and flattened item key
     *
     * @return array{0:string,1:string}
     */
    private function splitKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return ['messages', $key];
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Resolves an explicit, runtime or configured locale before falling back
     */
    private function resolveLocale(?string $locale): string
    {
        $resolved = $locale ?? $this->localeOverride ?? $this->defaultLocale();
        $resolved = trim($resolved);

        return $resolved !== '' ? $resolved : $this->fallbackLocale();
    }

    /**
     * Resolves source values and current overrides for one exact locale and group
     *
     * @return array<string, string>
     */
    private function resolvedLines(string $group, string $locale): array
    {
        return array_replace(
            $this->sources->lines($locale, $group),
            $this->overrides->group($locale, $group),
        );
    }

    /**
     * Returns groups available from either the requested or fallback locale
     *
     * @return list<string>
     */
    private function groupNames(string $locale): array
    {
        $fallbackLocale = $this->fallbackLocale();
        $names = [
            ...$this->sources->groups($locale),
            ...$this->sources->groups($fallbackLocale),
            ...$this->overrides->groups($locale),
            ...$this->overrides->groups($fallbackLocale),
        ];

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * Returns the configured default locale or the stable framework default
     */
    private function defaultLocale(): string
    {
        $locale = trim($this->config->defaultLocale);

        return $locale !== '' ? $locale : 'en';
    }

    /**
     * Returns the configured fallback locale or the stable framework default
     */
    private function fallbackLocale(): string
    {
        $locale = trim($this->config->fallbackLocale);

        return $locale !== '' ? $locale : 'en';
    }
}
