<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Core\Config;

use function strtolower;
use function trim;

final class ConfigLocaleUrlStrategy implements LocaleUrlStrategyInterface
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function enabled(): bool
    {
        return $this->config->bool('localization.url.enabled', false);
    }

    public function localeParameter(): string
    {
        $value = $this->config->string('localization.url.locale_parameter', 'locale');
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : 'locale';
    }

    public function localizedRouteName(string $baseRouteName): string
    {
        $prefix = $this->config->string('localization.url.localized_route_name_prefix');

        if (!is_string($prefix) || trim($prefix) === '') {
            $legacyPrefix = $this->config->string('localization.url.prefix_route_name', 'localized.');
            $prefix = is_string($legacyPrefix) ? $legacyPrefix : 'localized.';
        }

        return $prefix . $baseRouteName;
    }

    public function shouldUseLocalizedRoute(string $locale): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        if ($this->config->bool('localization.url.include_default_locale', false)) {
            return true;
        }

        return strtolower(trim($locale)) !== $this->defaultLocale();
    }

    private function defaultLocale(): string
    {
        $default = $this->config->string('localization.default_locale', 'en');
        $default = is_string($default) ? trim($default) : 'en';

        return strtolower($default !== '' ? $default : 'en');
    }
}
