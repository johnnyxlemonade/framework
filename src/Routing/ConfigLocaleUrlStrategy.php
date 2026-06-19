<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Localization\Config\LocalizationConfig;

use function strtolower;
use function trim;

final class ConfigLocaleUrlStrategy implements LocaleUrlStrategyInterface
{
    public function __construct(
        private readonly LocalizationConfig $config,
    ) {}

    public function enabled(): bool
    {
        return $this->config->url->enabled;
    }

    public function localeParameter(): string
    {
        $value = trim($this->config->url->localeParameter);

        return $value !== '' ? $value : 'locale';
    }

    public function localizedRouteName(string $baseRouteName): string
    {
        return $this->config->url->localizedRouteNamePrefix . $baseRouteName;
    }

    public function shouldUseLocalizedRoute(string $locale): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        if ($this->config->url->includeDefaultLocale) {
            return true;
        }

        return strtolower(trim($locale)) !== $this->defaultLocale();
    }

    private function defaultLocale(): string
    {
        $default = trim($this->config->defaultLocale);

        return strtolower($default !== '' ? $default : 'en');
    }
}
