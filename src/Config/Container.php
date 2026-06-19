<?php

declare(strict_types=1);

use Lemonade\Framework\Container\Config\ContainerConfigDefinition;

return ContainerConfigDefinition::create()
    ->autowireFallbackWarning(false);
