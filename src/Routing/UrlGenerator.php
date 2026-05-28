<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Localization\LocaleResolverInterface;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;

use function array_key_exists;
use function is_scalar;
use function trim;

final class UrlGenerator
{
    public function __construct(
        private readonly Router $router,
        private readonly ?LocaleResolverInterface $localeResolver = null,
        private readonly ?LocaleUrlStrategyInterface $localeUrlStrategy = null,
    ) {}

    /**
     * @param array<string, scalar|null> $params
     */
    public function route(string $name, array $params = []): string
    {
        return $this->router->url($name, $params);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function localizedRoute(string $name, array $params = [], ?string $locale = null): string
    {
        $strategy = $this->localeUrlStrategy;
        if (!$strategy instanceof LocaleUrlStrategyInterface) {
            return $this->route($name, $params);
        }

        $localeParameter = $strategy->localeParameter();
        $resolvedLocale = $this->resolveLocale($locale, $params, $localeParameter);
        unset($params[$localeParameter]);

        if ($resolvedLocale === null || !$strategy->shouldUseLocalizedRoute($resolvedLocale)) {
            return $this->route($name, $params);
        }

        $localizedName = $strategy->localizedRouteName($name);
        $localizedParams = [$localeParameter => $resolvedLocale] + $params;

        try {
            return $this->route($localizedName, $localizedParams);
        } catch (RouteNotFoundException) {
            // Fallback keeps app functional when localized variant is not explicitly registered.
            return $this->route($name, $params);
        }
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function resolveLocale(?string $locale, array $params, string $localeParameter): ?string
    {
        if (is_string($locale) && trim($locale) !== '') {
            return trim($locale);
        }

        if (array_key_exists($localeParameter, $params) && is_scalar($params[$localeParameter])) {
            $value = trim((string) $params[$localeParameter]);

            return $value !== '' ? $value : null;
        }

        if (!$this->localeResolver instanceof LocaleResolverInterface) {
            return null;
        }

        $value = trim($this->localeResolver->resolve());

        return $value !== '' ? $value : null;
    }
}
