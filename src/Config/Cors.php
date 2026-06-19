<?php

declare(strict_types=1);

use Lemonade\Framework\Http\Config\CorsConfigDefinition;

return CorsConfigDefinition::create()
    ->disabled()
    ->allowedOrigins([])
    ->allowedMethods([])
    ->allowedHeaders([])
    ->exposedHeaders([])
    ->allowCredentials(false)
    ->maxAge(null);
