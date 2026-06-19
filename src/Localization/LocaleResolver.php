<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Localization\Config\LocalizationConfig;
use RuntimeException;

use function array_unique;
use function array_values;
use function in_array;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

final class LocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LocalizationConfig $config,
    ) {}

    public function resolve(): string
    {
        $supported = $this->supportedLocales();

        $runtime = $this->normalizeLocale($this->translator->locale());

        if ($runtime !== null && in_array($runtime, $supported, true)) {
            return $runtime;
        }

        $default = $this->configuredLocale($this->config->defaultLocale, 'default_locale');

        if (in_array($default, $supported, true)) {
            return $default;
        }

        $fallback = $this->configuredLocale($this->config->fallbackLocale, 'fallback_locale');

        if (in_array($fallback, $supported, true)) {
            return $fallback;
        }

        return $supported[0];
    }

    /**
     * @return non-empty-list<string>
     */
    private function supportedLocales(): array
    {
        $locales = [];
        foreach ($this->config->supportedLocales as $locale) {
            $normalized = $this->normalizeLocale($locale);

            if ($normalized === null) {
                continue;
            }

            $locales[] = $normalized;
        }

        $locales = array_values(array_unique($locales));

        if ($locales === []) {
            throw new RuntimeException('Localization supported locales must contain at least one valid locale.');
        }

        return $locales;
    }

    private function configuredLocale(string $value, string $label): string
    {
        $locale = $this->normalizeLocale($value);

        if ($locale === null) {
            throw new RuntimeException(sprintf('Localization config key "%s" must contain a valid locale.', $label));
        }

        return $locale;
    }

    private function normalizeLocale(mixed $locale): ?string
    {
        if (!is_string($locale)) {
            return null;
        }

        $locale = strtolower(trim($locale));

        return $locale === '' ? null : $locale;
    }
}
