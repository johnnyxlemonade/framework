<?php

declare(strict_types=1);

use Lemonade\Framework\Cache\Config\CacheConfigDefinition;

return CacheConfigDefinition::create()
    ->defaultStore('file')
    ->fileStore('cache/framework', 'lemonade', 300);
