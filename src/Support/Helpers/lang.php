<?php

declare(strict_types=1);

if (!function_exists('lang')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->lang().
     *
     * @param array<string, scalar|null> $replacements
     */
    function lang(string $key, array $replacements = [], ?string $locale = null): string
    {
        throw new LogicException('The global lang() helper no longer resolves framework services. In views use $helpers->lang(); elsewhere inject TranslatorInterface explicitly.');
    }
}

if (!function_exists('current_locale')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->currentLocale().
     */
    function current_locale(string $default = 'en'): string
    {
        throw new LogicException('The global current_locale() helper no longer resolves framework services. In views use $helpers->currentLocale(); elsewhere inject TranslatorInterface explicitly.');
    }
}

if (!function_exists('lang_group')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->langGroup().
     *
     * @return array<string, string>
     */
    function lang_group(string $group, ?string $locale = null): array
    {
        throw new LogicException('The global lang_group() helper no longer resolves framework services. In views use $helpers->langGroup(); elsewhere inject TranslatorInterface explicitly.');
    }
}

if (!function_exists('lang_all')) {
    /**
     * @deprecated removed; use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->langAll().
     *
     * @return array<string, array<string, string>>
     */
    function lang_all(?string $locale = null): array
    {
        throw new LogicException('The global lang_all() helper no longer resolves framework services. In views use $helpers->langAll(); elsewhere inject TranslatorInterface explicitly.');
    }
}
