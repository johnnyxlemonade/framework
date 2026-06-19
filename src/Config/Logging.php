<?php

declare(strict_types=1);

use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;

return LoggingConfigDefinition::create()
    ->appEnabled()
    ->appPath('app.log')
    ->appLevel('info')
    ->appDays(7)
    ->errorEnabled()
    ->errorPath('error.log')
    ->errorLevel('error')
    ->errorDays(7)
    ->errorLogNotFound(false)
    ->requestEnabled(false)
    ->requestPath('request.log')
    ->requestLevel('info')
    ->requestDays(7)
    ->requestMinStatus(0)
    ->benchmarkEnabled(false)
    ->benchmarkPath('benchmark.log')
    ->benchmarkLevel('debug')
    ->benchmarkDays(7);
