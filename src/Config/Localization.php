<?php

declare(strict_types=1);

return [
    'default_locale' => 'en',
    'fallback_locale' => 'en',
    'supported_locales' => ['en'],
    'url' => [
        'localized_route_name_prefix' => 'localized.',
        'route_prefix' => '/{locale}',
        'locale_parameter' => 'locale',
    ],
];
