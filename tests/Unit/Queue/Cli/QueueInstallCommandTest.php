<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue\Cli;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Driver\Mysql\MysqlIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSqlEscaper;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteSchemaGrammar;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteSqlEscaper;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Queue\Cli\QueueInstallCommand;
use Lemonade\Framework\Queue\Config\QueueConfig;
use Lemonade\Framework\Queue\Config\QueueDatabaseConfig;
use Lemonade\Framework\Queue\Internal\DatabaseQueueDialect;
use Lemonade\Framework\Queue\Internal\DatabaseQueueSchemaValidator;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use PHPUnit\Framework\TestCase;

final class QueueInstallCommandTest extends TestCase
{
    /** @var resource|null */
    private $stdout = null;

    protected function tearDown(): void
    {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }

        $this->stdout = null;
    }

    public function testCommandCanBeResolvedThroughContainerWithSchemaDependency(): void
    {
        $container = new Container();
        $db = new QueueInstallCommandDatabaseDriverSpy();
        $connection = new QueueInstallCommandConnectionStub();
        $container->singleton(Schema::class, $this->mysqlSchema($db));
        $container->singleton(DatabaseDriverInterface::class, $db);
        $dialect = DatabaseQueueDialect::fromConfig($this->mysqlConfig());
        $container->singleton(DatabaseQueueDialect::class, $dialect);
        $container->singleton(
            DatabaseQueueSchemaValidator::class,
            new DatabaseQueueSchemaValidator($db, $connection, $dialect),
        );
        $container->singleton(QueueConfig::class, $this->queueConfig());

        self::assertInstanceOf(QueueInstallCommand::class, $container->get(QueueInstallCommand::class));
    }

    public function testRunUsesSchemaLayerAndKeepsMysqlIndexesInline(): void
    {
        $db = new QueueInstallCommandDatabaseDriverSpy();
        $command = $this->command(
            schema: $this->mysqlSchema($db),
            config: $this->queueConfig(),
            db: $db,
            connection: new QueueInstallCommandConnectionStub(),
            databaseConfig: $this->mysqlConfig(),
        );

        self::assertSame(0, $command->run([]));
        self::assertSame("Queue tables ready: system_queue_job, system_queue_failed_job\n", $this->stdoutContents());

        self::assertCount(2, $db->queries);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `system_queue_job`', $db->queries[0]);
        self::assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $db->queries[0]);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $db->queries[0]);
        self::assertStringContainsString('`payload` LONGTEXT NOT NULL', $db->queries[0]);
        self::assertStringContainsString('`reservation_token` CHAR(64) NULL', $db->queries[0]);
        self::assertStringContainsString('`reserved_until` BIGINT UNSIGNED NULL', $db->queries[0]);
        self::assertStringContainsString('KEY `idx_queue_claim` (`queue_name`, `available_at`, `reserved_until`, `id`)', $db->queries[0]);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `system_queue_failed_job`', $db->queries[1]);
        self::assertStringContainsString('`job_id` BIGINT UNSIGNED NULL', $db->queries[1]);
        self::assertStringContainsString('KEY `idx_failed_queue` (`queue_name`, `failed_at`)', $db->queries[1]);
    }

    public function testRunUsesSchemaLayerAndSplitsSqliteIndexesIntoIdempotentStatements(): void
    {
        $db = new QueueInstallCommandDatabaseDriverSpy();
        $databaseConfig = $this->sqliteConfig();
        $command = $this->command(
            schema: $this->sqliteSchema($db),
            config: $this->queueConfig(),
            db: $db,
            connection: new QueueInstallCommandConnectionStub(),
            databaseConfig: $databaseConfig,
        );

        self::assertSame(0, $command->run([]));
        self::assertSame("Queue tables ready: system_queue_job, system_queue_failed_job\n", $this->stdoutContents());

        self::assertCount(4, $db->queries);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS "system_queue_job"', $db->queries[0]);
        self::assertStringContainsString('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $db->queries[0]);
        self::assertStringContainsString('"payload" TEXT NOT NULL', $db->queries[0]);
        self::assertStringContainsString('"reservation_token" CHAR(64) NULL', $db->queries[0]);
        self::assertStringContainsString('"reserved_until" INTEGER NULL', $db->queries[0]);
        self::assertStringNotContainsString('INDEX', $db->queries[0]);
        self::assertSame(
            'CREATE INDEX IF NOT EXISTS "idx_queue_claim" ON "system_queue_job" ("queue_name", "available_at", "reserved_until", "id")',
            $db->queries[1],
        );
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS "system_queue_failed_job"', $db->queries[2]);
        self::assertStringContainsString('"job_id" INTEGER NULL', $db->queries[2]);
        self::assertSame(
            'CREATE INDEX IF NOT EXISTS "idx_failed_queue" ON "system_queue_failed_job" ("queue_name", "failed_at")',
            $db->queries[3],
        );
    }

    public function testRunUsesConfiguredQueueTableNames(): void
    {
        $db = new QueueInstallCommandDatabaseDriverSpy();
        $command = $this->command(
            schema: $this->mysqlSchema($db),
            config: $this->queueConfig('custom_queue_job', 'custom_queue_failed_job'),
            db: $db,
            connection: new QueueInstallCommandConnectionStub(),
            databaseConfig: $this->mysqlConfig(),
        );

        self::assertSame(0, $command->run([]));
        self::assertSame("Queue tables ready: custom_queue_job, custom_queue_failed_job\n", $this->stdoutContents());

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `custom_queue_job`', $db->queries[0]);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `custom_queue_failed_job`', $db->queries[1]);
    }

    public function testRunCreatesSqliteTablesAndIsIdempotent(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);
        $connection = new PdoConnection($config, new Benchmark());
        $schema = $this->pdoSqliteSchema($config, $connection);
        $identifierEscaper = new SqliteIdentifierEscaper($config->prefix());
        $db = new PdoDatabaseDriver(
            $connection,
            $identifierEscaper,
            new IdentifierProtector($identifierEscaper),
        );
        $command = $this->command(
            schema: $schema,
            config: $this->queueConfig(),
            db: $db,
            connection: $connection,
            databaseConfig: $config,
        );

        self::assertSame(0, $command->run([]));
        $command->run([]);
        self::assertSame(
            "Queue tables ready: system_queue_job, system_queue_failed_job\n"
            . "Queue tables ready: system_queue_job, system_queue_failed_job\n",
            $this->stdoutContents(),
        );

        $pdo = $connection->pdo();
        $tableStatement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        $indexStatement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' ORDER BY name");

        self::assertInstanceOf(\PDOStatement::class, $tableStatement);
        self::assertInstanceOf(\PDOStatement::class, $indexStatement);

        $tables = $tableStatement->fetchAll(\PDO::FETCH_COLUMN);
        $indexes = $indexStatement->fetchAll(\PDO::FETCH_COLUMN);

        self::assertContains('system_queue_job', $tables);
        self::assertContains('system_queue_failed_job', $tables);
        self::assertContains('idx_queue_claim', $indexes);
        self::assertContains('idx_failed_queue', $indexes);
    }

    public function testExistingPreLeaseTableIsRejectedWithoutAutomaticAlter(): void
    {
        $config = $this->sqliteConfig();
        $connection = new PdoConnection($config, new Benchmark());
        $connection->statement(
            'CREATE TABLE system_queue_job ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, queue_name TEXT NOT NULL, payload TEXT NOT NULL, '
            . 'attempts INTEGER NOT NULL, available_at INTEGER NOT NULL, reserved_at INTEGER NULL, '
            . 'created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)',
        );
        $identifierEscaper = new SqliteIdentifierEscaper($config->prefix());
        $db = new PdoDatabaseDriver(
            $connection,
            $identifierEscaper,
            new IdentifierProtector($identifierEscaper),
        );
        $command = $this->command(
            schema: $this->pdoSqliteSchema($config, $connection),
            config: $this->queueConfig(),
            db: $db,
            connection: $connection,
            databaseConfig: $config,
        );

        try {
            $command->run([]);
            self::fail('Expected the pre-lease queue schema to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('incompatible with reservation leases', $exception->getMessage());
            $columns = $connection->select('PRAGMA table_info(system_queue_job)');
            $names = array_column($columns, 'name');
            self::assertNotContains('reservation_token', $names);
            self::assertNotContains('reserved_until', $names);
            self::assertSame(
                [],
                $connection->select(
                    "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'system_queue_failed_job'",
                ),
            );
        }
    }

    private function command(
        Schema $schema,
        QueueConfig $config,
        DatabaseDriverInterface $db,
        ConnectionInterface $connection,
        DatabaseConfig $databaseConfig,
    ): QueueInstallCommand {
        $dialect = DatabaseQueueDialect::fromConfig($databaseConfig);

        return new QueueInstallCommand(
            schema: $schema,
            db: $db,
            schemaValidator: new DatabaseQueueSchemaValidator($db, $connection, $dialect),
            config: $config,
            stdout: $this->stdout(),
        );
    }

    private function queueConfig(string $table = 'system_queue_job', string $failedTable = 'system_queue_failed_job'): QueueConfig
    {
        return new QueueConfig('sync', ['sync'], [], new QueueDatabaseConfig($table, $failedTable));
    }

    /**
     * @return resource
     */
    private function stdout()
    {
        if (is_resource($this->stdout)) {
            return $this->stdout;
        }

        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create temp stream.');
        }

        return $this->stdout = $stream;
    }

    private function stdoutContents(): string
    {
        if (!is_resource($this->stdout)) {
            return '';
        }

        rewind($this->stdout);
        $contents = stream_get_contents($this->stdout);

        return is_string($contents) ? $contents : '';
    }

    private function mysqlSchema(DatabaseDriverInterface $db): Schema
    {
        $config = $this->mysqlConfig();
        $escaper = new MysqlSqlEscaper(new MysqlIdentifierEscaper($config->prefix()));

        return new Schema(
            new SchemaCompiler(new MysqlSchemaGrammar($escaper, $config)),
            $db,
        );
    }

    private function sqliteSchema(DatabaseDriverInterface $db): Schema
    {
        $config = $this->sqliteConfig();

        return new Schema(
            new SchemaCompiler(new SqliteSchemaGrammar(
                new SqliteSqlEscaper(new SqliteIdentifierEscaper($config->prefix())),
                $config,
            )),
            $db,
        );
    }

    private function pdoSqliteSchema(DatabaseConfig $config, PdoConnection $connection): Schema
    {
        $identifierEscaper = new SqliteIdentifierEscaper($config->prefix());

        return new Schema(
            new SchemaCompiler(new SqliteSchemaGrammar(
                new SqliteSqlEscaper($identifierEscaper),
                $config,
            )),
            new PdoDatabaseDriver(
                $connection,
                $identifierEscaper,
                new IdentifierProtector($identifierEscaper),
            ),
        );
    }

    private function mysqlConfig(): DatabaseConfig
    {
        return DatabaseConfig::fromArray(['driver' => 'mysql']);
    }

    private function sqliteConfig(): DatabaseConfig
    {
        return DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);
    }
}

final class QueueInstallCommandDatabaseDriverSpy implements DatabaseDriverInterface
{
    /** @var list<string> */
    public array $queries = [];

    /** @var array<string, true> */
    private array $tables = [];

    public function tableExists(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    /**
     * @param array<int|string, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        unset($binds);
        $this->queries[] = $sql;

        if (preg_match('/CREATE TABLE IF NOT EXISTS [`"]?([^`"\s(]+)/i', $sql, $matches) === 1) {
            $this->tables[$matches[1]] = true;
        }

        return true;
    }

    /**
     * @param array<int|string, mixed>|false $binds
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array|false $binds = false): \Generator
    {
        unset($sql, $binds);

        yield from [];
    }

    public function simple_query(string $sql): bool
    {
        unset($sql);

        return true;
    }

    public function affected_rows(): int
    {
        return 0;
    }

    public function insert_id(): int|string|null
    {
        return null;
    }

    public function escape(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return "''";
        }

        return "'" . (string) $value . "'";
    }

    public function escape_str(string $value, bool $like = false): string
    {
        unset($like);

        return $value;
    }

    public function escape_like_str(string $value): string
    {
        return $value;
    }

    public function escape_identifiers(string $item): string
    {
        return $item;
    }

    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        unset($prefixSingle, $protectIdentifiers, $fieldExists);

        return $item;
    }

    public function platform(): string
    {
        return 'test';
    }

    public function version(): string
    {
        return 'test';
    }
}

final class QueueInstallCommandConnectionStub implements ConnectionInterface
{
    public function select(string $sql, array $bindings = []): array
    {
        unset($bindings);

        if (str_starts_with($sql, 'PRAGMA table_info')) {
            return array_map(
                static fn (string $name): array => ['name' => $name],
                [
                    'id',
                    'job_id',
                    'queue_name',
                    'payload',
                    'attempts',
                    'available_at',
                    'reserved_at',
                    'reservation_token',
                    'reserved_until',
                    'created_at',
                    'updated_at',
                    'error_message',
                    'failed_at',
                ],
            );
        }

        return [];
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        unset($sql, $bindings);

        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        unset($sql, $bindings);

        return 0;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}
