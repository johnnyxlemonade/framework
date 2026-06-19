<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization\Config;

final class LocalizationUrlConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $localizedRouteNamePrefix,
        public readonly string $routePrefix,
        public readonly string $localeParameter,
        public readonly bool $includeDefaultLocale,
    ) {}
}
