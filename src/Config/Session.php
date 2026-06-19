<?php

declare(strict_types=1);

use Lemonade\Framework\Session\Config\SessionConfigDefinition;

return SessionConfigDefinition::create()
    ->driver('native')
    ->cookie('LEMONADE_SESSION')
    ->lifetime(7200)
    ->nativePath('sessions')
    ->filePath('sessions')
    ->databaseTable('sessions')
    ->redisHost('127.0.0.1')
    ->redisPort(6379)
    ->redisDatabase(0)
    ->redisPassword(null)
    ->redisPrefix('sess:')
    ->redisTimeout(2.5);
