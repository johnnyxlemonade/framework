<?php

declare(strict_types=1);

use Lemonade\Framework\Queue\Config\QueueConfigDefinition;

return QueueConfigDefinition::create()
    ->defaultTransport('sync')
    ->transports(['sync'])
    ->databaseTable('system_queue_job')
    ->failedTable('system_queue_failed_job');
