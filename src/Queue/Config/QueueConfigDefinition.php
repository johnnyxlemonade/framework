<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds typed queue configuration entries for transport topology, handlers, and database lease policy.
 */
final class QueueConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an empty queue definition that can be composed with framework or application defaults.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the registry key under which queue definitions are merged.
     */
    public static function moduleKey(): string
    {
        return 'queue';
    }

    /**
     * Selects the transport used when dispatch does not name one explicitly.
     */
    public function defaultTransport(string $transport): self
    {
        return $this->set('default', $transport);
    }

    /**
     * Replaces the enabled built-in transport list while preserving caller order.
     *
     * @param list<string> $transports
     */
    public function transports(array $transports): self
    {
        return $this->set('transports', array_values($transports));
    }

    /**
     * Selects the active database queue table without creating or migrating it.
     */
    public function databaseTable(string $table): self
    {
        return $this->set('database.table', $table);
    }

    /**
     * Selects the terminal failed-job table without creating or migrating it.
     */
    public function failedTable(string $table): self
    {
        return $this->set('database.failed_table', $table);
    }

    /**
     * Configures how long one database delivery remains exclusively owned without renewal.
     */
    public function databaseLeaseSeconds(int $seconds): self
    {
        return $this->set('database.lease_seconds', $seconds);
    }

    /**
     * Configures how many database reservations may execute before crash recovery quarantines the job.
     */
    public function databaseMaxAttempts(int $attempts): self
    {
        return $this->set('database.max_attempts', $attempts);
    }

    /**
     * Associates a message class with the handler resolved when that message is executed.
     *
     * @param (callable(object): void)|string $handler
     */
    public function handler(string $messageClass, callable|string $handler): self
    {
        return $this->set("handlers.{$messageClass}", $handler);
    }
}
