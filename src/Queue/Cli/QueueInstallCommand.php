<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Cli;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Database\DatabaseDriverInterface;

final class QueueInstallCommand implements CommandInterface
{
    public function __construct(
        private readonly DatabaseDriverInterface $db,
        private readonly Config $config,
    ) {}

    public function name(): string
    {
        return 'queue:install';
    }

    public function description(): string
    {
        return 'Create queue tables for database transport.';
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        unset($args);

        $table = $this->config->string('queue.database.table', 'system_queue_job') ?? 'system_queue_job';
        $failed = $this->config->string('queue.database.failed_table', 'system_queue_failed_job') ?? 'system_queue_failed_job';

        $sqlMain = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue_name VARCHAR(120) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                available_at INT UNSIGNED NOT NULL,
                reserved_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                updated_at INT UNSIGNED NOT NULL,
                INDEX idx_queue_available (queue_name, available_at, reserved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $this->db->protect_identifiers($table, true, null, false),
        );

        $sqlFailed = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_id BIGINT UNSIGNED NULL,
                queue_name VARCHAR(120) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                error_message TEXT NOT NULL,
                failed_at INT UNSIGNED NOT NULL,
                INDEX idx_failed_queue (queue_name, failed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $this->db->protect_identifiers($failed, true, null, false),
        );

        $this->db->query($sqlMain);
        $this->db->query($sqlFailed);

        fwrite(STDOUT, sprintf("Queue tables ready: %s, %s\n", $table, $failed));

        return 0;
    }
}
