<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Database\Connection\ConnectionFactory;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseDriverRegistry;
use Lemonade\Framework\Database\DatabaseFactory;
use Lemonade\Framework\Database\DatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteIdentifierEscaper;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseServiceProviderTest extends TestCase
{
    public function testContextualRegistrationPreservesPreviouslyRegisteredDatabaseDrivers(): void
    {
        $container = new Container();
        $config = $this->config();
        $connection = new PdoConnection($config, new DatabaseInstrumentation());
        (new DatabaseServiceProvider())->register($container);
        (new PdoDatabaseServiceProvider())->register($container);
        (new SqliteDatabaseServiceProvider())->register($container);
        $container->instance(DatabaseConfig::class, $config);
        $container->instance(ConnectionInterface::class, $connection);
        $registry = $container->get(DatabaseDriverRegistry::class);

        $container->when(ContextualDatabaseConsumer::class)->needs(Database::class)->give(Database::class);
        $container->freeze();

        self::assertInstanceOf(PdoDatabaseDriver::class, $container->get(DatabaseDriverInterface::class));
        self::assertSame($registry, $container->get(DatabaseDriverRegistry::class));
        self::assertSame($connection, $container->get(ContextualDatabaseConsumer::class)->database->connection());
    }

    public function testContainerManagedDatabaseAndModelShareTransactionConnection(): void
    {
        [$container, $connection, $driver, $factory] = $this->container();

        /** @var Database $database */
        $database = $container->get(Database::class);
        /** @var DatabaseDriverInterface $resolvedDriver */
        $resolvedDriver = $container->get(DatabaseDriverInterface::class);
        $model = new class ($resolvedDriver) extends Model {
            protected string $table = 'framework_model_transaction_records';

            /** @var list<string> */
            protected array $allowedFields = ['value'];

            public function driver(): DatabaseDriverInterface
            {
                return $this->db;
            }
        };

        $driver->query('CREATE TABLE framework_model_transaction_records (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');

        self::assertSame($connection, $database->connection());
        self::assertSame($driver, $model->driver());
        self::assertTrue($database->tableExists('framework_model_transaction_records'));
        self::assertFalse($database->tableExists('missing_framework_table'));

        try {
            $database->transaction(function () use ($model): void {
                $model->insert(['value' => 'rolled-back']);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertSame(0, $model->countWhere(['value' => 'rolled-back']));

        $database->transaction(function () use ($model): void {
            $model->insert(['value' => 'committed']);
        });

        self::assertSame(1, $model->countWhere(['value' => 'committed']));

        $explicit = $factory->create($this->config());

        self::assertNotSame($database->connection(), $explicit->connection());
    }

    /**
     * @return array{0:Container,1:PdoConnection,2:PdoDatabaseDriver,3:DatabaseFactory}
     */
    private function container(): array
    {
        $container = new Container();
        $config = $this->config();
        $instrumentation = new DatabaseInstrumentation();
        $connection = new PdoConnection($config, $instrumentation);
        $driver = $this->driver($connection);
        $registry = new DatabaseDriverRegistry();
        $registry->registerDriver(
            Driver::Pdo,
            function (ConnectionInterface $connection): DatabaseDriverInterface {
                if (!$connection instanceof PdoConnection) {
                    throw new RuntimeException('Expected a PDO connection.');
                }

                return $this->driver($connection);
            },
        );
        $factory = new DatabaseFactory(
            new ConnectionFactory($instrumentation),
            $registry,
            $container,
        );

        (new DatabaseServiceProvider())->register($container);
        $container->singleton(DatabaseConfig::class, $config);
        $container->singleton(ConnectionInterface::class, $connection);
        $container->singleton(DatabaseDriverInterface::class, $driver);
        $container->singleton(DatabaseDriverRegistry::class, $registry);
        $container->singleton(DatabaseFactory::class, $factory);

        return [$container, $connection, $driver, $factory];
    }

    private function config(): DatabaseConfig
    {
        return DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);
    }

    private function driver(PdoConnection $connection): PdoDatabaseDriver
    {
        $escaper = new SqliteIdentifierEscaper('');

        return new PdoDatabaseDriver(
            connection: $connection,
            identifierEscaper: $escaper,
            identifierProtector: new IdentifierProtector($escaper),
        );
    }
}

final readonly class ContextualDatabaseConsumer
{
    public function __construct(public Database $database)
    {
    }
}
