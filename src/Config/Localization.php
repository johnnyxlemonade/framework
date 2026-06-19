<?php

declare(strict_types=1);

use Lemonade\Framework\Localization\Config\LocalizationConfigDefinition;

return LocalizationConfigDefinition::create()
    ->defaultLocale('en')
    ->fallbackLocale('en')
    ->supportedLocales(['en'])
    ->urlEnabled(false)
    ->localizedRouteNamePrefix('localized.')
    ->routePrefix('/{locale}')
    ->localeParameter('locale')
    ->includeDefaultLocale(false);
