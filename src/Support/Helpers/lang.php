<?php

declare(strict_types=1);

use Lemonade\Framework\Localization\TranslatorInterface;

if (!function_exists('lang')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->lang().
     *
     * @param array<string, scalar|null> $replacements
     */
    function lang(string $key, array $replacements = [], ?string $locale = null): string
    {
        $translator = service(TranslatorInterface::class);

        if (!$translator instanceof TranslatorInterface) {
            return $key;
        }

        return $translator->get($key, $replacements, $locale);
    }
}

if (!function_exists('current_locale')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->currentLocale().
     */
    function current_locale(string $default = 'en'): string
    {
        $translator = service(TranslatorInterface::class);
        if ($translator instanceof TranslatorInterface) {
            $runtime = $translator->locale();
            if (is_string($runtime) && trim($runtime) !== '') {
                return trim($runtime);
            }
        }

        $configuredValue = config('localization.default_locale', $default);
        $configured = is_scalar($configuredValue) ? trim((string) $configuredValue) : $default;

        return $configured !== '' ? $configured : $default;
    }
}

if (!function_exists('lang_group')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->langGroup().
     *
     * @return array<string, string>
     */
    function lang_group(string $group, ?string $locale = null): array
    {
        $translator = service(TranslatorInterface::class);
        if (!$translator instanceof TranslatorInterface) {
            return [];
        }

        return $translator->group($group, $locale);
    }
}

if (!function_exists('lang_all')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->langAll().
     *
     * @return array<string, array<string, string>>
     */
    function lang_all(?string $locale = null): array
    {
        $translator = service(TranslatorInterface::class);
        if (!$translator instanceof TranslatorInterface) {
            return [];
        }

        return $translator->all($locale);
    }
}
