<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteIdentifierEscaper;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Queue\MessageCodecInterface;
use Lemonade\Framework\Queue\MessageCodecRegistry;
use Lemonade\Framework\Queue\MessageSerializer;
use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\Transport\DatabaseQueueTransport;
use PHPUnit\Framework\TestCase;

final class DatabaseQueueTransportTest extends TestCase
{
    private const string SECRET = 'database-queue-test-secret-with-entropy';

    public function testAuthenticatedMessageCompletesDatabaseLifecycle(): void
    {
        [$connection, $transport] = $this->transport();
        $transport->enqueue(new QueuedMessage(new DatabaseTransportMessage('job-1'), 'critical'));

        $reserved = $transport->dequeue('critical');

        self::assertInstanceOf(QueuedMessage::class, $reserved);
        self::assertEquals(new DatabaseTransportMessage('job-1'), $reserved->message());
        self::assertNotNull($reserved->rawPayload());
        self::assertSame(1, $reserved->attempts());

        $transport->ack($reserved);

        self::assertSame(0, $this->countRows($connection, 'system_queue_job'));
        self::assertSame(0, $this->countRows($connection, 'system_queue_failed_job'));
    }

    public function testLegacySerializedPoisonMessageIsFailedWithoutMagicMethods(): void
    {
        DatabaseTransportWakeupPayload::$wakeups = 0;
        DatabaseTransportWakeupPayload::$destructs = 0;
        [$connection, $transport] = $this->transport();
        $legacy = sprintf(
            'O:%d:"%s":0:{}',
            strlen(DatabaseTransportWakeupPayload::class),
            DatabaseTransportWakeupPayload::class,
        );
        $this->insertRawJob($connection, base64_encode($legacy));

        self::assertNull($transport->dequeue('default'));
        self::assertSame(0, DatabaseTransportWakeupPayload::$wakeups);
        self::assertSame(0, DatabaseTransportWakeupPayload::$destructs);
        self::assertSame(0, $this->countRows($connection, 'system_queue_job'));

        $failed = $connection->select('SELECT payload, attempts, error_message FROM system_queue_failed_job');
        self::assertCount(1, $failed);
        self::assertSame(base64_encode($legacy), $failed[0]['payload']);
        self::assertSame(1, (int) $failed[0]['attempts']);
        self::assertStringNotContainsString($legacy, (string) $failed[0]['error_message']);
    }

    public function testHandlerFailurePreservesOriginalRawEnvelopeAndBoundsDiagnostic(): void
    {
        [$connection, $transport] = $this->transport();
        $transport->enqueue(new QueuedMessage(new DatabaseTransportMessage('job-2')));
        $stored = $connection->select('SELECT payload FROM system_queue_job')[0]['payload'];
        self::assertIsString($stored);
        $reserved = $transport->dequeue('default');
        self::assertInstanceOf(QueuedMessage::class, $reserved);

        $transport->fail($reserved, str_repeat('x', 4_096));

        $failed = $connection->select('SELECT payload, error_message FROM system_queue_failed_job');
        self::assertCount(1, $failed);
        self::assertSame($stored, $failed[0]['payload']);
        self::assertSame(2_048, strlen((string) $failed[0]['error_message']));
        self::assertSame(0, $this->countRows($connection, 'system_queue_job'));
    }

    public function testReservationPreventsASecondWorkerFromReceivingSameJob(): void
    {
        [$connection, $first] = $this->transport();
        $second = $this->transportForConnection($connection);
        $first->enqueue(new QueuedMessage(new DatabaseTransportMessage('only-once')));

        $firstDelivery = $first->dequeue('default');
        $secondDelivery = $second->dequeue('default');

        self::assertInstanceOf(QueuedMessage::class, $firstDelivery);
        self::assertNull($secondDelivery);
    }

    public function testInterleavedWorkersCannotBothReserveTheSameSelectedRow(): void
    {
        $serializer = $this->serializer();
        $connection = new InterleavingQueueConnection(
            $serializer->encode(new DatabaseTransportMessage('race')),
        );
        $driver = new QueueIdentifierDriverStub();
        $first = new DatabaseQueueTransport($driver, $serializer, $connection);
        $second = new DatabaseQueueTransport($driver, $serializer, $connection);
        $secondDelivery = null;
        $connection->beforeFirstReservation = static function () use ($second, &$secondDelivery): void {
            $secondDelivery = $second->dequeue('default');
        };

        $firstDelivery = $first->dequeue('default');

        self::assertNull($firstDelivery);
        self::assertInstanceOf(QueuedMessage::class, $secondDelivery);
        self::assertSame(1, $connection->successfulReservations);
    }

    public function testPoisonTransitionFailureRollsBackReservationForSafeRetry(): void
    {
        [$connection, $transport] = $this->transport();
        $this->insertRawJob($connection, 'not-json');
        $connection->statement('DROP TABLE system_queue_failed_job');

        try {
            $transport->dequeue('default');
            self::fail('Expected failed-job storage failure.');
        } catch (\Throwable) {
            $rows = $connection->select('SELECT reserved_at FROM system_queue_job');
            self::assertCount(1, $rows);
            self::assertNull($rows[0]['reserved_at']);
        }
    }

    /**
     * @return array{PdoConnection, DatabaseQueueTransport}
     */
    private function transport(): array
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);
        $connection = new PdoConnection($config, new Benchmark());
        $this->createSchema($connection);

        return [$connection, $this->transportForConnection($connection)];
    }

    private function transportForConnection(PdoConnection $connection): DatabaseQueueTransport
    {
        $escaper = new SqliteIdentifierEscaper('');
        $driver = new PdoDatabaseDriver(
            connection: $connection,
            identifierEscaper: $escaper,
            identifierProtector: new IdentifierProtector($escaper),
        );

        return new DatabaseQueueTransport(
            db: $driver,
            serializer: $this->serializer(),
            connection: $connection,
        );
    }

    private function serializer(): MessageSerializer
    {
        $registry = new MessageCodecRegistry();
        $registry->register(
            alias: 'database.message',
            messageClass: DatabaseTransportMessage::class,
            codec: new DatabaseTransportMessageCodec(),
        );

        return new MessageSerializer($registry, self::SECRET);
    }

    private function createSchema(PdoConnection $connection): void
    {
        $connection->statement(
            'CREATE TABLE system_queue_job ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'queue_name TEXT NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, '
            . 'available_at INTEGER NOT NULL, reserved_at INTEGER NULL, '
            . 'created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)',
        );
        $connection->statement(
            'CREATE TABLE system_queue_failed_job ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, job_id INTEGER NULL, '
            . 'queue_name TEXT NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, '
            . 'error_message TEXT NOT NULL, failed_at INTEGER NOT NULL)',
        );
    }

    private function insertRawJob(PdoConnection $connection, string $payload): void
    {
        $now = time();
        $connection->statement(
            'INSERT INTO system_queue_job '
            . '(queue_name, payload, attempts, available_at, reserved_at, created_at, updated_at) '
            . 'VALUES (?, ?, 0, ?, NULL, ?, ?)',
            ['default', $payload, $now, $now, $now],
        );
    }

    private function countRows(PdoConnection $connection, string $table): int
    {
        $rows = $connection->select(sprintf('SELECT COUNT(*) AS count FROM %s', $table));

        return (int) ($rows[0]['count'] ?? 0);
    }
}

final readonly class DatabaseTransportMessage
{
    public function __construct(public string $id)
    {
    }
}

final class DatabaseTransportMessageCodec implements MessageCodecInterface
{
    public function encode(object $message): array
    {
        if (!$message instanceof DatabaseTransportMessage) {
            throw new \InvalidArgumentException('Unexpected database transport test message.');
        }

        return ['id' => $message->id];
    }

    public function decode(array $payload): object
    {
        $id = $payload['id'] ?? null;
        if (!is_string($id)) {
            throw new \InvalidArgumentException('Invalid database transport test payload.');
        }

        return new DatabaseTransportMessage($id);
    }
}

final class DatabaseTransportWakeupPayload
{
    public static int $wakeups = 0;
    public static int $destructs = 0;

    public function __wakeup(): void
    {
        self::$wakeups++;
    }

    public function __destruct()
    {
        self::$destructs++;
    }
}

final class InterleavingQueueConnection implements ConnectionInterface
{
    /** @var (callable(): void)|null */
    public $beforeFirstReservation = null;

    public int $successfulReservations = 0;

    private bool $reserved = false;

    public function __construct(private readonly string $payload)
    {
    }

    public function select(string $sql, array $bindings = []): array
    {
        unset($sql, $bindings);

        if ($this->reserved) {
            return [];
        }

        return [[
            'id' => 1,
            'payload' => $this->payload,
            'attempts' => 0,
        ]];
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        unset($sql, $bindings);

        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        unset($bindings);

        if (!str_starts_with($sql, 'UPDATE')) {
            throw new \LogicException('Unexpected interleaving test statement.');
        }

        $interleave = $this->beforeFirstReservation;
        $this->beforeFirstReservation = null;
        if ($interleave !== null) {
            $interleave();
        }

        if ($this->reserved) {
            return 0;
        }

        $this->reserved = true;
        $this->successfulReservations++;

        return 1;
    }

    public function beginTransaction(): void
    {
    }

    public function commit(): void
    {
    }

    public function rollBack(): void
    {
    }

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

    public function reconnect(): void
    {
    }

    public function close(): void
    {
    }

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}

final class QueueIdentifierDriverStub implements DatabaseDriverInterface
{
    public function tableExists(string $table): bool
    {
        unset($table);

        return false;
    }

    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        unset($sql, $binds);

        return false;
    }

    public function cursor(string $sql, array|false $binds = false): \Generator
    {
        unset($sql, $binds);

        yield from [];
    }

    public function simple_query(string $sql): bool
    {
        unset($sql);

        return false;
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
        return (string) $value;
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
