<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class QueueConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'queue';
    }

    public function defaultTransport(string $transport): self
    {
        return $this->set('default', $transport);
    }

    /**
     * @param list<string> $transports
     */
    public function transports(array $transports): self
    {
        return $this->set('transports', array_values($transports));
    }

    public function databaseTable(string $table): self
    {
        return $this->set('database.table', $table);
    }

    public function failedTable(string $table): self
    {
        return $this->set('database.failed_table', $table);
    }

    public function handler(string $messageClass, callable|string $handler): self
    {
        return $this->set("handlers.{$messageClass}", $handler);
    }
}
