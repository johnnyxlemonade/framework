<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

final class QueueConfigResolver
{
    public function resolve(QueueConfigDefinition ...$definitions): QueueConfig
    {
        $defaultTransport = 'sync';
        $transports = ['sync'];
        $handlers = [];
        $table = 'system_queue_job';
        $failedTable = 'system_queue_failed_job';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('default', $data)) {
                $defaultTransport = ConfigValueNormalizer::nonEmptyStringOr($data['default'], $defaultTransport);
            }
            if (array_key_exists('transports', $data)) {
                $transports = ConfigValueNormalizer::stringList($data['transports']);
            }
            if (isset($data['handlers']) && is_array($data['handlers'])) {
                foreach ($data['handlers'] as $messageClass => $handler) {
                    if (is_string($messageClass)) {
                        $handlers[$messageClass] = $handler;
                    }
                }
            }

            $database = is_array($data['database'] ?? null) ? $data['database'] : [];
            if (array_key_exists('table', $database)) {
                $table = ConfigValueNormalizer::nonEmptyStringOr($database['table'], $table);
            }
            if (array_key_exists('failed_table', $database)) {
                $failedTable = ConfigValueNormalizer::nonEmptyStringOr($database['failed_table'], $failedTable);
            }
        }

        if ($transports === []) {
            $transports = ['sync'];
        }

        return new QueueConfig(
            defaultTransport: $defaultTransport,
            transports: $transports,
            handlers: $handlers,
            database: new QueueDatabaseConfig($table, $failedTable),
        );
    }

}
