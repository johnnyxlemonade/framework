<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Cli;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Queue\Config\QueueConfig;
use Lemonade\Framework\Queue\Internal\DatabaseQueueSchemaValidator;

/**
 * Creates fresh database queue tables and validates existing tables without altering them implicitly.
 */
final class QueueInstallCommand implements CommandInterface
{
    /**
     * @var resource|null
     */
    private readonly mixed $stdout;

    /**
     * Creates an installer bound to the configured schema, supported dialect, and compatibility validator.
     */
    public function __construct(
        private readonly Schema $schema,
        private readonly DatabaseDriverInterface $db,
        private readonly DatabaseQueueSchemaValidator $schemaValidator,
        private readonly QueueConfig $config,
        mixed $stdout = null,
    ) {
        if ($stdout !== null && !is_resource($stdout)) {
            throw new \InvalidArgumentException('QueueInstallCommand stdout must be a valid resource.');
        }

        $this->stdout = $stdout;
    }

    /**
     * Returns the stable CLI command name used by the command registry.
     */
    public function name(): string
    {
        return 'queue:install';
    }

    /**
     * Returns the operator-facing summary shown by CLI command discovery.
     */
    public function description(): string
    {
        return 'Create queue tables for database transport.';
    }

    /**
     * Creates missing tables, rejects incompatible existing schemas, and reports readiness only after validation.
     *
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        unset($args);

        $table = $this->config->database->table;
        $failed = $this->config->database->failedTable;

        $activeExists = $this->db->tableExists($table);
        $failedExists = $this->db->tableExists($failed);

        if ($activeExists) {
            $this->schemaValidator->assertActiveCompatible($table);
        }
        if ($failedExists) {
            $this->schemaValidator->assertFailedCompatible($failed);
        }

        if (!$activeExists) {
            $created = $this->schema->create($table, $this->defineQueueJobTable(...), ifNotExists: true);
            if (!$created) {
                throw new \RuntimeException(sprintf('Queue table "%s" could not be created.', $table));
            }
        }

        if (!$failedExists) {
            $created = $this->schema->create($failed, $this->defineFailedJobTable(...), ifNotExists: true);
            if (!$created) {
                throw new \RuntimeException(sprintf('Queue table "%s" could not be created.', $failed));
            }
        }

        $this->schemaValidator->assertCompatible($table, $failed);

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
        $table->char('reservation_token', 64)->nullable();
        $table->unsignedBigInteger('reserved_until')->nullable();
        $table->unsignedInteger('created_at');
        $table->unsignedInteger('updated_at');
        $table->index(
            ['queue_name', 'available_at', 'reserved_until', 'id'],
            'idx_queue_claim',
            ifNotExists: true,
        );
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
