<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

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
                $defaultTransport = $this->stringOr($data['default'], $defaultTransport);
            }
            if (array_key_exists('transports', $data)) {
                $transports = $this->stringList($data['transports']);
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
                $table = $this->stringOr($database['table'], $table);
            }
            if (array_key_exists('failed_table', $database)) {
                $failedTable = $this->stringOr($database['failed_table'], $failedTable);
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

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }
        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $normalized = trim((string) $item);
            if ($normalized === '' || in_array($normalized, $list, true)) {
                continue;
            }
            $list[] = $normalized;
        }

        return $list;
    }
}
