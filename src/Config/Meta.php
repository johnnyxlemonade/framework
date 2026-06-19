<?php

declare(strict_types=1);

use Lemonade\Framework\Component\Meta\Config\MetaConfigDefinition;

return MetaConfigDefinition::create()
    ->websiteName('website')
    ->charset('UTF-8')
    ->viewport('width=device-width, initial-scale=1')
    ->rating('General')
    ->titleSeparator(' - ');
