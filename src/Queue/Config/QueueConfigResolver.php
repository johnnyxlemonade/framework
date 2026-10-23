<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Merges queue definitions while rejecting every explicit transport topology that cannot dispatch safely.
 */
final class QueueConfigResolver
{
    /**
     * Returns a validated queue configuration, defaulting to sync only when transport settings are omitted.
     *
     * @throws \InvalidArgumentException When a transport value is malformed, unsupported, or inconsistent.
     */
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
                $defaultTransport = $this->normalizeTransportName(
                    $data['default'],
                    'Default queue transport',
                );
            }
            if (array_key_exists('transports', $data)) {
                $transports = $this->normalizeTransports($data['transports']);
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

        return new QueueConfig(
            defaultTransport: $defaultTransport,
            transports: $transports,
            handlers: $handlers,
            database: new QueueDatabaseConfig($table, $failedTable),
        );
    }

    private function normalizeTransportName(mixed $value, string $label): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(sprintf(
                '%s must be a non-empty string; received %s.',
                $label,
                get_debug_type($value),
            ));
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function normalizeTransports(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Queue transports must be a list; received %s.',
                get_debug_type($value),
            ));
        }

        $transports = [];

        foreach ($value as $transport) {
            $normalized = $this->normalizeTransportName($transport, 'Queue transport');

            if (!in_array($normalized, $transports, true)) {
                $transports[] = $normalized;
            }
        }

        return $transports;
    }

}
