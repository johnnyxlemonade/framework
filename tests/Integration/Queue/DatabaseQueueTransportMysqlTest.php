<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Integration\Queue;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Mysql\MysqlIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Queue\Exception\QueueLeaseLostException;
use Lemonade\Framework\Queue\Internal\DatabaseQueueDialect;
use Lemonade\Framework\Queue\Internal\DatabaseQueueSchemaValidator;
use Lemonade\Framework\Queue\MessageCodecInterface;
use Lemonade\Framework\Queue\MessageCodecRegistry;
use Lemonade\Framework\Queue\MessageSerializer;
use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\Transport\DatabaseQueueTransport;
use PHPUnit\Framework\TestCase;

final class DatabaseQueueTransportMysqlTest extends TestCase
{
    public function testMysqlLeaseRecoveryFencesTheStaleWorker(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('pdo_mysql is required for the MySQL queue lifecycle test.');
        }

        $suffix = bin2hex(random_bytes(6));
        $table = 'queue_lease_' . $suffix;
        $failedTable = 'queue_failed_' . $suffix;
        $connection = $this->connection();

        try {
            $connection->serverVersion();
        } catch (\Throwable $exception) {
            self::markTestSkipped('MySQL queue lifecycle database is unavailable: ' . $exception->getMessage());
        }

        try {
            $connection->statement(sprintf(
                'CREATE TABLE `%s` ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, '
                . 'queue_name VARCHAR(120) NOT NULL, payload LONGTEXT NOT NULL, attempts INT UNSIGNED NOT NULL, '
                . 'available_at INT UNSIGNED NOT NULL, reserved_at INT UNSIGNED NULL, '
                . 'reservation_token CHAR(64) NULL, reserved_until BIGINT UNSIGNED NULL, '
                . 'created_at INT UNSIGNED NOT NULL, updated_at INT UNSIGNED NOT NULL)',
                $table,
            ));
            $connection->statement(sprintf(
                'CREATE TABLE `%s` ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, job_id BIGINT UNSIGNED NULL, '
                . 'queue_name VARCHAR(120) NOT NULL, payload LONGTEXT NOT NULL, attempts INT UNSIGNED NOT NULL, '
                . 'error_message TEXT NOT NULL, failed_at INT UNSIGNED NOT NULL)',
                $failedTable,
            ));

            $config = $this->config();
            $escaper = new MysqlIdentifierEscaper('');
            $driver = new PdoDatabaseDriver(
                connection: $connection,
                identifierEscaper: $escaper,
                identifierProtector: new IdentifierProtector($escaper),
            );
            $serializer = $this->serializer();
            $dialect = DatabaseQueueDialect::fromConfig($config);
            $validator = new DatabaseQueueSchemaValidator($driver, $connection, $dialect);
            $first = new DatabaseQueueTransport(
                db: $driver,
                serializer: $serializer,
                connection: $connection,
                dialect: $dialect,
                schemaValidator: $validator,
                table: $table,
                failedTable: $failedTable,
            );
            $second = new DatabaseQueueTransport(
                db: $driver,
                serializer: $serializer,
                connection: $connection,
                dialect: $dialect,
                schemaValidator: $validator,
                table: $table,
                failedTable: $failedTable,
            );

            $first->enqueue(new QueuedMessage(new MysqlLeaseMessage('mysql')));
            $stale = $first->dequeue('default');
            self::assertInstanceOf(QueuedMessage::class, $stale);
            $connection->statement(sprintf('UPDATE `%s` SET reserved_until = 0', $table));
            $current = $second->dequeue('default');
            self::assertInstanceOf(QueuedMessage::class, $current);
            self::assertSame(2, $current->attempts());

            try {
                $first->ack($stale);
                self::fail('Expected stale MySQL acknowledgement to lose ownership.');
            } catch (QueueLeaseLostException) {
                $second->ack($current);
                self::assertSame([], $connection->select(sprintf('SELECT id FROM `%s`', $table)));
            }
        } finally {
            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS `%s`', $table));
                $connection->statement(sprintf('DROP TABLE IF EXISTS `%s`', $failedTable));
            } catch (\Throwable) {
                // The test is already skipped or failed because the integration database is unavailable.
            }
        }
    }

    private function connection(): PdoConnection
    {
        return new PdoConnection($this->config(), new Benchmark());
    }

    private function config(): DatabaseConfig
    {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $database = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');

        return DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'mysql',
            'dsn' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                is_string($host) ? $host : 'db',
                is_string($port) ? $port : '3306',
                is_string($database) ? $database : 'db',
            ),
            'username' => is_string($username) ? $username : 'db',
            'password' => is_string($password) ? $password : 'db',
        ]);
    }

    private function serializer(): MessageSerializer
    {
        $registry = new MessageCodecRegistry();
        $registry->register(
            alias: 'mysql.lease.message',
            messageClass: MysqlLeaseMessage::class,
            codec: new MysqlLeaseMessageCodec(),
        );

        return new MessageSerializer($registry, 'mysql-queue-lease-test-secret');
    }
}

final readonly class MysqlLeaseMessage
{
    public function __construct(public string $value)
    {
    }
}

final class MysqlLeaseMessageCodec implements MessageCodecInterface
{
    public function encode(object $message): array
    {
        if (!$message instanceof MysqlLeaseMessage) {
            throw new \InvalidArgumentException('Unexpected MySQL lease message.');
        }

        return ['value' => $message->value];
    }

    public function decode(array $payload): object
    {
        $value = $payload['value'] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Invalid MySQL lease message payload.');
        }

        return new MysqlLeaseMessage($value);
    }
}
