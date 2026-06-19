<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization\Config;

use RuntimeException;

final class LocalizationConfigResolver
{
    public function resolve(LocalizationConfigDefinition ...$definitions): LocalizationConfig
    {
        $defaultLocale = 'en';
        $fallbackLocale = 'en';
        $supportedLocales = ['en'];
        $urlEnabled = false;
        $localizedRouteNamePrefix = 'localized.';
        $routePrefix = '/{locale}';
        $localeParameter = 'locale';
        $includeDefaultLocale = false;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('default_locale', $data)) {
                $defaultLocale = $this->localeOr($data['default_locale'], $defaultLocale);
            }

            if (array_key_exists('fallback_locale', $data)) {
                $fallbackLocale = $this->localeOr($data['fallback_locale'], $fallbackLocale);
            }

            if (array_key_exists('supported_locales', $data)) {
                $supportedLocales = $this->supportedLocalesList($data['supported_locales']);
            }

            $url = is_array($data['url'] ?? null) ? $data['url'] : [];

            if (array_key_exists('enabled', $url)) {
                $urlEnabled = $this->toBool($url['enabled'], $urlEnabled);
            }

            if (array_key_exists('localized_route_name_prefix', $url)) {
                $localizedRouteNamePrefix = $this->stringOr($url['localized_route_name_prefix'], $localizedRouteNamePrefix);
            }

            if (array_key_exists('route_prefix', $url)) {
                $routePrefix = $this->stringOr($url['route_prefix'], $routePrefix);
            }

            if (array_key_exists('locale_parameter', $url)) {
                $localeParameter = $this->stringOr($url['locale_parameter'], $localeParameter);
            }

            if (array_key_exists('include_default_locale', $url)) {
                $includeDefaultLocale = $this->toBool($url['include_default_locale'], $includeDefaultLocale);
            }
        }

        return new LocalizationConfig(
            defaultLocale: $defaultLocale,
            fallbackLocale: $fallbackLocale,
            supportedLocales: $supportedLocales,
            url: new LocalizationUrlConfig(
                enabled: $urlEnabled,
                localizedRouteNamePrefix: $localizedRouteNamePrefix,
                routePrefix: $routePrefix,
                localeParameter: $localeParameter,
                includeDefaultLocale: $includeDefaultLocale,
            ),
        );
    }

    /**
     * @return non-empty-list<string>
     */
    private function supportedLocalesList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('Localization supported locales must be a non-empty array.');
        }

        $locales = [];

        foreach ($value as $locale) {
            $normalized = $this->nullableLocale($locale);

            if ($normalized !== null && !in_array($normalized, $locales, true)) {
                $locales[] = $normalized;
            }
        }

        if ($locales === []) {
            throw new RuntimeException('Localization supported locales must contain at least one valid locale.');
        }

        return $locales;
    }

    private function localeOr(mixed $value, string $default): string
    {
        return $this->nullableLocale($value) ?? $default;
    }

    private function nullableLocale(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $default;
    }
}
