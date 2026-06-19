<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class LocalizationConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'localization';
    }

    public function defaultLocale(string $locale): self
    {
        return $this->set('default_locale', $locale);
    }

    public function fallbackLocale(string $locale): self
    {
        return $this->set('fallback_locale', $locale);
    }

    /**
     * @param list<string|int|float|bool> $locales
     */
    public function supportedLocales(array $locales): self
    {
        return $this->set('supported_locales', array_values($locales));
    }

    public function urlEnabled(bool $enabled = true): self
    {
        return $this->set('url.enabled', $enabled);
    }

    public function localizedRouteNamePrefix(string $prefix): self
    {
        return $this->set('url.localized_route_name_prefix', $prefix);
    }

    public function routePrefix(string $prefix): self
    {
        return $this->set('url.route_prefix', $prefix);
    }

    public function localeParameter(string $parameter): self
    {
        return $this->set('url.locale_parameter', $parameter);
    }

    public function includeDefaultLocale(bool $enabled = true): self
    {
        return $this->set('url.include_default_locale', $enabled);
    }
}
