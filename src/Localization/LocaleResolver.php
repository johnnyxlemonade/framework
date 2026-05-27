<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Core\Config;
use RuntimeException;

use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

final class LocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Config $config,
    ) {}

    public function resolve(): string
    {
        $supported = $this->supportedLocales();

        $runtime = $this->normalizeLocale($this->translator->locale());

        if ($runtime !== null && in_array($runtime, $supported, true)) {
            return $runtime;
        }

        $default = $this->configuredLocale('localization.default_locale');

        if (in_array($default, $supported, true)) {
            return $default;
        }

        $fallback = $this->configuredLocale('localization.fallback_locale');

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
        $configured = $this->config->get('localization.supported_locales');

        if (!is_array($configured)) {
            throw new RuntimeException('Config key "localization.supported_locales" must be a non-empty array.');
        }

        $locales = [];

        foreach ($configured as $locale) {
            $normalized = $this->normalizeLocale($locale);

            if ($normalized === null) {
                continue;
            }

            $locales[] = $normalized;
        }

        $locales = array_values(array_unique($locales));

        if ($locales === []) {
            throw new RuntimeException('Config key "localization.supported_locales" must contain at least one valid locale.');
        }

        return $locales;
    }

    private function configuredLocale(string $key): string
    {
        $locale = $this->normalizeLocale($this->config->get($key));

        if ($locale === null) {
            throw new RuntimeException(sprintf('Config key "%s" must contain a valid locale.', $key));
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
