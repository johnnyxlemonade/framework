<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Cli;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

final class QueueInstallCommand implements CommandInterface
{
    /** @var resource|null */
    private readonly mixed $stdout;

    public function __construct(
        private readonly Schema $schema,
        private readonly Config $config,
        mixed $stdout = null,
    ) {
        if ($stdout !== null && !is_resource($stdout)) {
            throw new \InvalidArgumentException('QueueInstallCommand stdout must be a valid resource.');
        }

        $this->stdout = $stdout;
    }

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

        $this->schema->create($table, $this->defineQueueJobTable(...), ifNotExists: true);
        $this->schema->create($failed, $this->defineFailedJobTable(...), ifNotExists: true);

        fwrite($this->stdout ?? STDOUT, sprintf("Queue tables ready: %s, %s\n", $table, $failed));

        return 0;
    }

    private function defineQueueJobTable(TableBlueprint $table): void
    {
        $table->id();
        $table->string('queue_name', 120);
        $table->longText('payload');
        $table->unsignedInteger('attempts')->default(0);
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('created_at');
        $table->unsignedInteger('updated_at');
        $table->index(['queue_name', 'available_at', 'reserved_at'], 'idx_queue_available', ifNotExists: true);
    }

    private function defineFailedJobTable(TableBlueprint $table): void
    {
        $table->id();
        $table->unsignedBigInteger('job_id')->nullable();
        $table->string('queue_name', 120);
        $table->longText('payload');
        $table->unsignedInteger('attempts')->default(0);
        $table->text('error_message');
        $table->unsignedInteger('failed_at');
        $table->index(['queue_name', 'failed_at'], 'idx_failed_queue', ifNotExists: true);
    }
}
