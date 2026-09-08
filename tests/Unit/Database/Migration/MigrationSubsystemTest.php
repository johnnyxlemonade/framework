<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Migration;

use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
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
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Migration\Cli\MigrateCommand;
use Lemonade\Framework\Database\Migration\Cli\MigrationStatusCommand;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Database\Migration\MigrationRunner;
use Lemonade\Framework\Database\Migration\MigrationServiceProvider;
use Lemonade\Framework\Database\Migration\MigrationStateRepository;
use Lemonade\Framework\Database\Migration\MigrationState;
use Lemonade\Framework\Database\Migration\MigrationStatus;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use PHPUnit\Framework\TestCase;

final class MigrationSubsystemTest extends TestCase
{
    /** @var resource|null */
    private $stdout = null;
    /** @var resource|null */
    private $stderr = null;

    protected function setUp(): void
    {
        TestMigration::reset();
        DeferredMigration::reset();
        FailingMigration::reset();
        DependentMigration::reset();
    }

    protected function tearDown(): void
    {
        foreach ([$this->stdout, $this->stderr] as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $this->stdout = null;
        $this->stderr = null;
    }

    public function testRegistryUsesClassStringsWithoutInstantiatingAndSortsIdentifiers(): void
    {
        $registry = new MigrationRegistry($this->container());
        $registry->register(DeferredMigration::class);
        $registry->register(TestMigration::class);

        self::assertSame(['20260908090000_test', '20260908091500_deferred'], $registry->identifiers());
        self::assertSame(0, TestMigration::$instances);
        self::assertSame(0, DeferredMigration::$instances);
        self::assertInstanceOf(TestMigration::class, $registry->get('20260908090000_test'));
        self::assertSame(1, TestMigration::$instances);
        self::assertSame(0, DeferredMigration::$instances);
    }

    public function testRegistryRejectsInvalidClassAndIdentifiers(): void
    {
        $registry = new MigrationRegistry($this->container());
        try {
            (new \ReflectionMethod($registry, 'register'))->invoke($registry, \stdClass::class);
            self::fail('Expected non-migration class to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('must implement', $exception->getMessage());
        }

        foreach ([EmptyIdentifierMigration::class, InvalidFormatMigration::class, InvalidTimestampMigration::class] as $class) {
            try {
                (new MigrationRegistry($this->container()))->register($class);
                self::fail(sprintf('Expected %s to be rejected.', $class));
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testRegistryRejectsDuplicateIdentifier(): void
    {
        $registry = new MigrationRegistry($this->container());
        $registry->register(TestMigration::class);

        $this->expectException(\LogicException::class);
        $registry->register(DuplicateIdentifierMigration::class);
    }

    public function testRunnerRunsOnlyPendingMigrationsInIdentifierOrder(): void
    {
        [$container, $driver, $schema] = $this->migrationContainer();
        $registry = $container->get(MigrationRegistry::class);
        $registry->register(DeferredMigration::class);
        $registry->register(TestMigration::class);
        $state = $container->get(MigrationStateRepository::class);
        $state->ensureTable();
        $state->record('20260908090000_test');

        self::assertSame(['20260908091500_deferred'], $container->get(MigrationRunner::class)->migrate());
        self::assertSame(0, TestMigration::$instances);
        self::assertSame(1, DeferredMigration::$instances);
        self::assertSame(['20260908090000_test', '20260908091500_deferred'], $state->applied());
        unset($driver, $schema);
    }

    public function testRunnerRecordsOnlySuccessfulMigrationAndStopsAfterFailure(): void
    {
        [$container, $driver, $schema] = $this->migrationContainer();
        $registry = $container->get(MigrationRegistry::class);
        $registry->register(FailingMigration::class);
        $registry->register(DeferredMigration::class);
        $state = $container->get(MigrationStateRepository::class);

        try {
            $container->get(MigrationRunner::class)->migrate();
            self::fail('Expected failing migration to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('migration failed', $exception->getMessage());
        }

        self::assertSame([], $state->applied());
        self::assertSame(1, FailingMigration::$instances);
        self::assertSame(0, DeferredMigration::$instances);
        unset($driver, $schema);
    }

    public function testRunnerResolvesConstructorDependenciesOnlyForPendingMigration(): void
    {
        [$container, $driver, $schema] = $this->migrationContainer();
        $container->singleton(MigrationDependency::class, new MigrationDependency('ready'));
        $container->get(MigrationRegistry::class)->register(DependentMigration::class);

        self::assertSame(['20260908100000_dependent'], $container->get(MigrationRunner::class)->migrate());
        self::assertSame('ready', DependentMigration::$dependencyValue);
        unset($driver, $schema);
    }

    public function testStatusUsesValueObjectWithoutInstantiatingMigrations(): void
    {
        [$container, $driver, $schema] = $this->migrationContainer();
        $registry = $container->get(MigrationRegistry::class);
        $registry->register(DeferredMigration::class);
        $registry->register(TestMigration::class);
        $state = $container->get(MigrationStateRepository::class);
        $state->ensureTable();
        $state->record('20260908090000_test');
        $state->record('20260901090000_orphaned');

        $status = $container->get(MigrationRunner::class)->status();

        self::assertInstanceOf(MigrationStatus::class, $status);
        self::assertSame([
            '20260908090000_test' => MigrationState::Applied,
            '20260908091500_deferred' => MigrationState::Pending,
        ], $status->registered());
        self::assertSame(['20260901090000_orphaned'], $status->orphaned());
        self::assertTrue($status->databaseAvailable());
        self::assertSame(0, TestMigration::$instances);
        self::assertSame(0, DeferredMigration::$instances);
        unset($driver, $schema);
    }

    public function testStateTableCompilesForMysql(): void
    {
        $driver = new RecordingDriver();
        $config = DatabaseConfig::fromArray(['driver' => 'mysql']);
        $schema = new Schema(new SchemaCompiler(new MysqlSchemaGrammar(
            new MysqlSqlEscaper(new MysqlIdentifierEscaper('')),
            $config,
        )), $driver);

        (new MigrationStateRepository($driver, $schema))->ensureTable();

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `migrations`', $driver->queries[0]);
        self::assertStringContainsString('PRIMARY KEY (`migration`)', $driver->queries[0]);
    }

    public function testProviderAndApplicationProviderRegisterWithoutDatabaseResolution(): void
    {
        $container = $this->container();
        $container->singleton(CommandRegistry::class, new CommandRegistry($container));
        (new MigrationServiceProvider())->register($container);
        (new TestApplicationMigrationProvider())->register($container);

        self::assertTrue($container->get(CommandRegistry::class)->has('database:migrate'));
        self::assertTrue($container->get(CommandRegistry::class)->has('database:migrate:status'));
        self::assertSame(['20260908090000_test'], $container->get(MigrationRegistry::class)->identifiers());
        self::assertSame(0, TestMigration::$instances);
    }

    public function testCliCommandsReportMigrationAndStatusOutput(): void
    {
        [$container, $driver, $schema] = $this->migrationContainer();
        $container->get(MigrationRegistry::class)->register(TestMigration::class);
        $migrate = new MigrateCommand($container, $this->stdout(), $this->stderr());
        $status = new MigrationStatusCommand(
            $container->get(MigrationRegistry::class),
            $container,
            $this->stdout(),
            $this->stderr(),
        );

        self::assertSame(0, $migrate->run([]));
        self::assertSame(0, $migrate->run([]));
        $container->get(MigrationStateRepository::class)->record('20260901090000_orphaned');
        self::assertSame(0, $status->run([]));
        self::assertStringContainsString('Migrated: 20260908090000_test', $this->contents($this->stdout));
        self::assertStringContainsString('No pending migrations.', $this->contents($this->stdout));
        self::assertStringContainsString('APPLIED  20260908090000_test', $this->contents($this->stdout));
        self::assertStringContainsString('orphaned  20260901090000_orphaned', $this->contents($this->stdout));

        [$failingContainer, $failingDriver, $failingSchema] = $this->migrationContainer();
        $failingContainer->get(MigrationRegistry::class)->register(FailingMigration::class);
        self::assertSame(1, (new MigrateCommand($failingContainer, $this->stdout(), $this->stderr()))->run([]));
        self::assertStringContainsString('Migration failed: migration failed', $this->contents($this->stderr));
        unset($failingDriver, $failingSchema);
        unset($driver, $schema);
    }

    public function testCliCommandsReportClearDatabaseConfigurationErrors(): void
    {
        $container = $this->container();
        $container->singleton(DatabaseDriverInterface::class, static fn(): DatabaseDriverInterface => throw DatabaseException::invalidConfiguration('Database connection [default] is not configured.'));
        (new MigrationServiceProvider())->register($container);
        $container->get(MigrationRegistry::class)->register(TestMigration::class);

        self::assertSame(1, (new MigrateCommand($container, $this->stdout(), $this->stderr()))->run([]));
        self::assertSame(1, (new MigrationStatusCommand(
            $container->get(MigrationRegistry::class),
            $container,
            $this->stdout(),
            $this->stderr(),
        ))->run([]));
        self::assertStringContainsString('Cannot run migrations: no database connection is configured.', $this->contents($this->stderr));
        self::assertStringContainsString('Database state unavailable: no database connection is configured.', $this->contents($this->stderr));
        self::assertStringContainsString('UNKNOWN  20260908090000_test', $this->contents($this->stdout));
        self::assertSame(0, TestMigration::$instances);
    }

    /** @return array{0:Container, 1:PdoDatabaseDriver, 2:Schema} */
    private function migrationContainer(): array
    {
        $config = DatabaseConfig::fromArray(['driver' => 'pdo', 'dialect' => 'sqlite', 'dsn' => 'sqlite::memory:']);
        $connection = new PdoConnection($config, new Benchmark());
        $escaper = new SqliteIdentifierEscaper($config->prefix());
        $driver = new PdoDatabaseDriver($connection, $escaper, new IdentifierProtector($escaper));
        $schema = new Schema(new SchemaCompiler(new SqliteSchemaGrammar(new SqliteSqlEscaper($escaper), $config)), $driver);
        $container = $this->container();
        $container->singleton(DatabaseDriverInterface::class, $driver);
        $container->singleton(Schema::class, $schema);
        (new MigrationServiceProvider())->register($container);

        return [$container, $driver, $schema];
    }

    private function container(): Container
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);

        return $container;
    }

    /** @return resource */
    private function stdout() { return $this->stdout ??= $this->stream(); }
    /** @return resource */
    private function stderr() { return $this->stderr ??= $this->stream(); }
    /** @return resource */
    private function stream()
    {
        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create temp stream.');
        }

        return $stream;
    }
    /** @param resource|null $stream */
    private function contents($stream): string
    {
        if (!is_resource($stream)) {
            return '';
        }
        rewind($stream);
        $contents = stream_get_contents($stream);

        return is_string($contents) ? $contents : '';
    }
}

final class TestMigration implements MigrationInterface
{
    public static int $instances = 0;
    public static function reset(): void { self::$instances = 0; }
    public function __construct() { self::$instances++; }
    public static function identifier(): string { return '20260908090000_test'; }
    public function up(Schema $schema): void { unset($schema); }
}

final class DeferredMigration implements MigrationInterface
{
    public static int $instances = 0;
    public static function reset(): void { self::$instances = 0; }
    public function __construct() { self::$instances++; }
    public static function identifier(): string { return '20260908091500_deferred'; }
    public function up(Schema $schema): void { unset($schema); }
}

final class FailingMigration implements MigrationInterface
{
    public static int $instances = 0;
    public static function reset(): void { self::$instances = 0; }
    public function __construct() { self::$instances++; }
    public static function identifier(): string { return '20260908090000_failing'; }
    public function up(Schema $schema): void { unset($schema); throw new \RuntimeException('migration failed'); }
}

final class DependentMigration implements MigrationInterface
{
    public static ?string $dependencyValue = null;
    public static function reset(): void { self::$dependencyValue = null; }
    public function __construct(private readonly MigrationDependency $dependency) {}
    public static function identifier(): string { return '20260908100000_dependent'; }
    public function up(Schema $schema): void { unset($schema); self::$dependencyValue = $this->dependency->value; }
}

final class MigrationDependency { public function __construct(public readonly string $value) {} }
final class EmptyIdentifierMigration implements MigrationInterface { public static function identifier(): string { return ' '; } public function up(Schema $schema): void { unset($schema); } }
final class InvalidFormatMigration implements MigrationInterface { public static function identifier(): string { return 'migration'; } public function up(Schema $schema): void { unset($schema); } }
final class InvalidTimestampMigration implements MigrationInterface { public static function identifier(): string { return '20269999009999_invalid'; } public function up(Schema $schema): void { unset($schema); } }
final class DuplicateIdentifierMigration implements MigrationInterface { public static function identifier(): string { return '20260908090000_test'; } public function up(Schema $schema): void { unset($schema); } }

final class TestApplicationMigrationProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->get(MigrationRegistry::class)->register(TestMigration::class);
    }
}

final class RecordingDriver implements DatabaseDriverInterface
{
    /** @var list<string> */
    public array $queries = [];
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool { unset($binds); $this->queries[] = $sql; return true; }
    public function cursor(string $sql, array|false $binds = false): \Generator { unset($sql, $binds); yield from []; }
    public function simple_query(string $sql): bool { unset($sql); return true; }
    public function affected_rows(): int { return 0; }
    public function insert_id(): int|string|null { return null; }
    public function escape(mixed $value): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : ''; }
    public function escape_str(string $value, bool $like = false): string { unset($like); return $value; }
    public function escape_like_str(string $value): string { return $value; }
    public function escape_identifiers(string $item): string { return $item; }
    public function protect_identifiers(string $item, bool $prefixSingle = false, ?bool $protectIdentifiers = null, bool $fieldExists = true): string { unset($prefixSingle, $protectIdentifiers, $fieldExists); return $item; }
    public function platform(): string { return 'test'; }
    public function version(): string { return 'test'; }
}
