<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue\Cli;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
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
        $container->singleton(Schema::class, $this->mysqlSchema(new QueueInstallCommandDatabaseDriverSpy()));
        $container->singleton(Config::class, new Config());

        self::assertInstanceOf(QueueInstallCommand::class, $container->get(QueueInstallCommand::class));
    }

    public function testRunUsesSchemaLayerAndKeepsMysqlIndexesInline(): void
    {
        $db = new QueueInstallCommandDatabaseDriverSpy();
        $command = $this->command($this->mysqlSchema($db), new Config());

        self::assertSame(0, $command->run([]));
        self::assertSame("Queue tables ready: system_queue_job, system_queue_failed_job\n", $this->stdoutContents());

        self::assertCount(2, $db->queries);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `system_queue_job`', $db->queries[0]);
        self::assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $db->queries[0]);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $db->queries[0]);
        self::assertStringContainsString('`payload` LONGTEXT NOT NULL', $db->queries[0]);
        self::assertStringContainsString('KEY `idx_queue_available` (`queue_name`, `available_at`, `reserved_at`)', $db->queries[0]);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `system_queue_failed_job`', $db->queries[1]);
        self::assertStringContainsString('`job_id` BIGINT UNSIGNED NULL', $db->queries[1]);
        self::assertStringContainsString('KEY `idx_failed_queue` (`queue_name`, `failed_at`)', $db->queries[1]);
    }

    public function testRunUsesSchemaLayerAndSplitsSqliteIndexesIntoIdempotentStatements(): void
    {
        $db = new QueueInstallCommandDatabaseDriverSpy();
        $command = $this->command($this->sqliteSchema($db), new Config());

        self::assertSame(0, $command->run([]));
        self::assertSame("Queue tables ready: system_queue_job, system_queue_failed_job\n", $this->stdoutContents());

        self::assertCount(4, $db->queries);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS "system_queue_job"', $db->queries[0]);
        self::assertStringContainsString('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $db->queries[0]);
        self::assertStringContainsString('"payload" TEXT NOT NULL', $db->queries[0]);
        self::assertStringNotContainsString('INDEX', $db->queries[0]);
        self::assertSame(
            'CREATE INDEX IF NOT EXISTS "idx_queue_available" ON "system_queue_job" ("queue_name", "available_at", "reserved_at")',
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
            $this->mysqlSchema($db),
            new Config([
                'queue' => [
                    'database' => [
                        'table' => 'custom_queue_job',
                        'failed_table' => 'custom_queue_failed_job',
                    ],
                ],
            ]),
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
        $connection = new PdoConnection($config);
        $schema = $this->pdoSqliteSchema($config, $connection);
        $command = $this->command($schema, new Config());

        self::assertSame(0, $command->run([]));
        self::assertSame(0, $command->run([]));
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
        self::assertContains('idx_queue_available', $indexes);
        self::assertContains('idx_failed_queue', $indexes);
    }

    private function command(Schema $schema, Config $config): QueueInstallCommand
    {
        return new QueueInstallCommand($schema, $config, $this->stdout());
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
        $config = DatabaseConfig::fromArray(['driver' => 'mysql']);
        $escaper = new MysqlSqlEscaper(new MysqlIdentifierEscaper($config->prefix()));

        return new Schema(
            new SchemaCompiler(new MysqlSchemaGrammar($escaper, $config)),
            $db,
        );
    }

    private function sqliteSchema(DatabaseDriverInterface $db): Schema
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);

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
}

final class QueueInstallCommandDatabaseDriverSpy implements DatabaseDriverInterface
{
    /** @var list<string> */
    public array $queries = [];

    /**
     * @param array<int|string, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        unset($binds);
        $this->queries[] = $sql;

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
