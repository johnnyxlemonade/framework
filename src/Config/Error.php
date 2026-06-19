<?php

declare(strict_types=1);

use Lemonade\Framework\Http\Config\ErrorConfigDefinition;

return ErrorConfigDefinition::create()
    ->notFoundView('errors/404')
    ->internalServerErrorView('errors/500');
