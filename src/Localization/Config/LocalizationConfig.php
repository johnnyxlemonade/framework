<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization\Config;

final class LocalizationConfig
{
    /**
     * @param non-empty-list<string> $supportedLocales
     */
    public function __construct(
        public readonly string $defaultLocale,
        public readonly string $fallbackLocale,
        public readonly array $supportedLocales,
        public readonly LocalizationUrlConfig $url,
    ) {}
}
