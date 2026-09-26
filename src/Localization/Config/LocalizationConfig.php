<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization\Config;

final readonly class LocalizationConfig
{
    /**
     * @param non-empty-list<string> $supportedLocales
     */
    public function __construct(
        public string $defaultLocale,
        public string $fallbackLocale,
        public array $supportedLocales,
        public LocalizationUrlConfig $url,
    ) {}
}
