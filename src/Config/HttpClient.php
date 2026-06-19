<?php

declare(strict_types=1);

use Lemonade\Framework\Http\Config\HttpClientConfigDefinition;

return HttpClientConfigDefinition::create()
    ->timeout(10.0)
    ->connectTimeout(5.0)
    ->verifySsl();
