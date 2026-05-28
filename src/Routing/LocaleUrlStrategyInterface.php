<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

interface LocaleUrlStrategyInterface
{
    public function enabled(): bool;

    public function localeParameter(): string;

    public function localizedRouteName(string $baseRouteName): string;

    public function shouldUseLocalizedRoute(string $locale): bool;
}
