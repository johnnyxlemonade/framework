<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization\Config;

final readonly class LocalizationUrlConfig
{
    public function __construct(
        public bool $enabled,
        public string $localizedRouteNamePrefix,
        public string $routePrefix,
        public string $localeParameter,
        public bool $includeDefaultLocale,
    ) {}
}
