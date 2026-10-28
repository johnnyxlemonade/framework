<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteIdentifierEscaper;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Queue\Exception\QueueLeaseLostException;
use Lemonade\Framework\Queue\Internal\DatabaseQueueDialect;
use Lemonade\Framework\Queue\Internal\DatabaseQueueSchemaValidator;
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

    public function testReservationStoresRandomOwnerTokenAndDatabaseTimedLease(): void
    {
        [$connection, $transport] = $this->transport();
        $transport->enqueue(new QueuedMessage(new DatabaseTransportMessage('leased')), delaySeconds: 0);

        $reserved = $transport->dequeue('default');

        self::assertInstanceOf(QueuedMessage::class, $reserved);
        $row = $connection->select(
            'SELECT reservation_token, available_at, reserved_at, reserved_until, created_at, '
            . "CAST(strftime('%s', 'now') AS INTEGER) AS database_now "
            . 'FROM system_queue_job',
        )[0];
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', (string) $row['reservation_token']);
        self::assertSame((string) $row['reservation_token'], $reserved->reservationReceipt()?->ownerToken());
        self::assertSame((int) $row['created_at'], (int) $row['available_at']);
        self::assertGreaterThanOrEqual((int) $row['database_now'], (int) $row['reserved_at']);
        self::assertSame(300, (int) $row['reserved_until'] - (int) $row['reserved_at']);
    }

    public function testExpiredReservationCanBeRecoveredWithNewOwnerAndAttempt(): void
    {
        [$connection, $first] = $this->transport();
        $second = $this->transportForConnection($connection);
        $first->enqueue(new QueuedMessage(new DatabaseTransportMessage('recover')));
        $firstDelivery = $first->dequeue('default');
        self::assertInstanceOf(QueuedMessage::class, $firstDelivery);
        $firstToken = $firstDelivery->reservationReceipt()?->ownerToken();
        $this->expireReservation($connection, $firstDelivery->id());

        $secondDelivery = $second->dequeue('default');

        self::assertInstanceOf(QueuedMessage::class, $secondDelivery);
        self::assertSame(2, $secondDelivery->attempts());
        self::assertNotSame($firstToken, $secondDelivery->reservationReceipt()?->ownerToken());
    }

    public function testStaleAcknowledgementCannotDeleteReclaimedJob(): void
    {
        [$connection, $first, $second, $firstDelivery, $secondDelivery] = $this->reclaimedDelivery();

        try {
            $first->ack($firstDelivery);
            self::fail('Expected stale acknowledgement to lose its lease.');
        } catch (QueueLeaseLostException) {
            self::assertSame(1, $this->countRows($connection, 'system_queue_job'));
            self::assertSame(
                $secondDelivery->reservationReceipt()?->ownerToken(),
                $connection->select('SELECT reservation_token FROM system_queue_job')[0]['reservation_token'],
            );
        }
    }

    public function testStaleFailureRollsBackFailedInsertAndPreservesReclaimedJob(): void
    {
        [$connection, $first, $second, $firstDelivery, $secondDelivery] = $this->reclaimedDelivery();
        unset($second);

        try {
            $first->fail($firstDelivery, 'stale handler failure');
            self::fail('Expected stale failure to lose its lease.');
        } catch (QueueLeaseLostException) {
            self::assertSame(1, $this->countRows($connection, 'system_queue_job'));
            self::assertSame(0, $this->countRows($connection, 'system_queue_failed_job'));
            self::assertSame(
                $secondDelivery->reservationReceipt()?->ownerToken(),
                $connection->select('SELECT reservation_token FROM system_queue_job')[0]['reservation_token'],
            );
        }
    }

    public function testStaleRenewalCannotExtendReclaimedJob(): void
    {
        [$connection, $first, $second, $firstDelivery, $secondDelivery] = $this->reclaimedDelivery();
        unset($second);
        $before = $connection->select('SELECT reserved_until FROM system_queue_job')[0]['reserved_until'];

        try {
            $first->renew($firstDelivery);
            self::fail('Expected stale renewal to lose its lease.');
        } catch (QueueLeaseLostException) {
            self::assertSame($before, $connection->select('SELECT reserved_until FROM system_queue_job')[0]['reserved_until']);
            self::assertSame(
                $secondDelivery->reservationReceipt()?->ownerToken(),
                $connection->select('SELECT reservation_token FROM system_queue_job')[0]['reservation_token'],
            );
        }
    }

    public function testExpiredLeaseCannotBeAcknowledgedFailedOrRenewedBeforeReclaim(): void
    {
        foreach (['ack', 'fail', 'renew'] as $operation) {
            [$connection, $transport] = $this->transport();
            $transport->enqueue(new QueuedMessage(new DatabaseTransportMessage($operation)));
            $delivery = $transport->dequeue('default');
            self::assertInstanceOf(QueuedMessage::class, $delivery);
            $this->expireReservationAtDatabaseBoundary($connection, $delivery->id());

            try {
                if ($operation === 'ack') {
                    $transport->ack($delivery);
                } elseif ($operation === 'fail') {
                    $transport->fail($delivery, 'expired');
                } else {
                    $transport->renew($delivery);
                }

                self::fail(sprintf('Expected expired %s to lose its lease.', $operation));
            } catch (QueueLeaseLostException) {
                self::assertSame(1, $this->countRows($connection, 'system_queue_job'));
                self::assertSame(0, $this->countRows($connection, 'system_queue_failed_job'));
            }
        }
    }

    public function testRenewalExtendsLeaseAndKeepsJobReserved(): void
    {
        [$connection, $transport] = $this->transport();
        $other = $this->transportForConnection($connection);
        $transport->enqueue(new QueuedMessage(new DatabaseTransportMessage('renew')));
        $delivery = $transport->dequeue('default');
        self::assertInstanceOf(QueuedMessage::class, $delivery);
        $before = (int) $connection->select('SELECT reserved_until FROM system_queue_job')[0]['reserved_until'];

        $renewedUntil = $transport->renew($delivery);

        self::assertGreaterThan($before, $renewedUntil);
        self::assertNull($other->dequeue('default'));
    }

    public function testMaximumAttemptsQuarantinesExpiredJobWithoutAnotherDelivery(): void
    {
        [$connection, $transport] = $this->transport(maxAttempts: 3);
        $transport->enqueue(new QueuedMessage(new DatabaseTransportMessage('crash-loop')));

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $delivery = $transport->dequeue('default');
            self::assertInstanceOf(QueuedMessage::class, $delivery);
            self::assertSame($attempt, $delivery->attempts());
            $this->expireReservation($connection, $delivery->id());
        }

        self::assertNull($transport->dequeue('default'));
        self::assertSame(0, $this->countRows($connection, 'system_queue_job'));
        $failed = $connection->select('SELECT attempts, error_message FROM system_queue_failed_job');
        self::assertCount(1, $failed);
        self::assertSame(3, (int) $failed[0]['attempts']);
        self::assertStringContainsString('maximum attempts (3) exhausted', (string) $failed[0]['error_message']);
    }

    public function testInconsistentLeaseMetadataIsNotClaimed(): void
    {
        [$connection, $transport] = $this->transport();
        $this->insertRawJob($connection, $this->serializer()->encode(new DatabaseTransportMessage('inconsistent')));
        $connection->statement(
            'UPDATE system_queue_job SET reserved_at = 1, reservation_token = NULL, reserved_until = 0',
        );

        self::assertNull($transport->dequeue('default'));
        self::assertSame(0, (int) $connection->select('SELECT attempts FROM system_queue_job')[0]['attempts']);
    }

    public function testOdbcIsRejectedBeforeDatabaseQueueUse(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not support ODBC');

        DatabaseQueueDialect::fromConfig(DatabaseConfig::fromArray([
            'driver' => 'odbc',
        ]));
    }

    public function testConnectionFailureDuringClaimRollsBackReservation(): void
    {
        $config = $this->databaseConfig();
        $inner = new PdoConnection($config, new DatabaseInstrumentation());
        $this->createSchema($inner);
        $connection = new FailingReservationConnection($inner);
        $escaper = new SqliteIdentifierEscaper('');
        $driver = new PdoDatabaseDriver(
            connection: $inner,
            identifierEscaper: $escaper,
            identifierProtector: new IdentifierProtector($escaper),
        );
        $dialect = DatabaseQueueDialect::fromConfig($config);
        $transport = new DatabaseQueueTransport(
            db: $driver,
            serializer: $this->serializer(),
            connection: $connection,
            dialect: $dialect,
            schemaValidator: new DatabaseQueueSchemaValidator($driver, $connection, $dialect),
        );
        $transport->enqueue(new QueuedMessage(new DatabaseTransportMessage('disconnect')));
        $connection->failAfterReservation = true;

        try {
            $transport->dequeue('default');
            self::fail('Expected simulated connection loss during reservation.');
        } catch (\RuntimeException $exception) {
            self::assertSame('simulated connection loss', $exception->getMessage());
            $row = $inner->select('SELECT attempts, reserved_at, reservation_token, reserved_until FROM system_queue_job')[0];
            self::assertSame(0, (int) $row['attempts']);
            self::assertNull($row['reserved_at']);
            self::assertNull($row['reservation_token']);
            self::assertNull($row['reserved_until']);
        }
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
        $dialect = DatabaseQueueDialect::fromConfig(DatabaseConfig::fromArray(['driver' => 'mysql']));
        $validator = new DatabaseQueueSchemaValidator($driver, $connection, $dialect);
        $first = new DatabaseQueueTransport($driver, $serializer, $connection, $dialect, $validator);
        $second = new DatabaseQueueTransport($driver, $serializer, $connection, $dialect, $validator);
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
    private function transport(int $leaseSeconds = 300, int $maxAttempts = 3): array
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);
        $connection = new PdoConnection($config, new DatabaseInstrumentation());
        $this->createSchema($connection);

        return [
            $connection,
            $this->transportForConnection(
                connection: $connection,
                leaseSeconds: $leaseSeconds,
                maxAttempts: $maxAttempts,
            ),
        ];
    }

    private function transportForConnection(
        PdoConnection $connection,
        int $leaseSeconds = 300,
        int $maxAttempts = 3,
    ): DatabaseQueueTransport {
        $config = $this->databaseConfig();
        $escaper = new SqliteIdentifierEscaper('');
        $driver = new PdoDatabaseDriver(
            connection: $connection,
            identifierEscaper: $escaper,
            identifierProtector: new IdentifierProtector($escaper),
        );
        $dialect = DatabaseQueueDialect::fromConfig($config);

        return new DatabaseQueueTransport(
            db: $driver,
            serializer: $this->serializer(),
            connection: $connection,
            dialect: $dialect,
            schemaValidator: new DatabaseQueueSchemaValidator($driver, $connection, $dialect),
            leaseSeconds: $leaseSeconds,
            maxAttempts: $maxAttempts,
        );
    }

    /**
     * @return array{PdoConnection, DatabaseQueueTransport, DatabaseQueueTransport, QueuedMessage, QueuedMessage}
     */
    private function reclaimedDelivery(): array
    {
        [$connection, $first] = $this->transport();
        $second = $this->transportForConnection($connection);
        $first->enqueue(new QueuedMessage(new DatabaseTransportMessage('reclaimed')));
        $firstDelivery = $first->dequeue('default');
        self::assertInstanceOf(QueuedMessage::class, $firstDelivery);
        $this->expireReservation($connection, $firstDelivery->id());
        $secondDelivery = $second->dequeue('default');
        self::assertInstanceOf(QueuedMessage::class, $secondDelivery);

        return [$connection, $first, $second, $firstDelivery, $secondDelivery];
    }

    private function expireReservation(PdoConnection $connection, ?int $id): void
    {
        self::assertNotNull($id);
        $connection->statement(
            'UPDATE system_queue_job SET reserved_until = 0 WHERE id = ?',
            [$id],
        );
    }

    private function expireReservationAtDatabaseBoundary(PdoConnection $connection, ?int $id): void
    {
        self::assertNotNull($id);
        $connection->statement(
            "UPDATE system_queue_job SET reserved_until = CAST(strftime('%s', 'now') AS INTEGER) WHERE id = ?",
            [$id],
        );
    }

    private function databaseConfig(): DatabaseConfig
    {
        return DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);
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
            . 'available_at INTEGER NOT NULL, reserved_at INTEGER NULL, reservation_token TEXT NULL, '
            . 'reserved_until INTEGER NULL, '
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
            . '(queue_name, payload, attempts, available_at, reserved_at, reservation_token, reserved_until, created_at, updated_at) '
            . 'VALUES (?, ?, 0, ?, NULL, NULL, NULL, ?, ?)',
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
    private int $attempts = 0;
    private ?string $token = null;

    public function __construct(private readonly string $payload)
    {
    }

    public function select(string $sql, array $bindings = []): array
    {
        if (str_contains($sql, 'WHERE 1 = 0')) {
            return [];
        }

        if (str_starts_with($sql, 'SELECT payload, attempts')) {
            if (!$this->reserved || ($bindings[2] ?? null) !== $this->token) {
                return [];
            }

            return [[
                'payload' => $this->payload,
                'attempts' => $this->attempts,
            ]];
        }

        if ($this->reserved) {
            return [];
        }

        return [[
            'id' => 1,
            'attempts' => $this->attempts,
        ]];
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        unset($sql, $bindings);

        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
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
        $this->token = is_string($bindings[0] ?? null) ? $bindings[0] : null;
        $this->attempts++;
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

        return true;
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

final class FailingReservationConnection implements ConnectionInterface
{
    public bool $failAfterReservation = false;

    public function __construct(
        private readonly PdoConnection $inner,
    ) {
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->inner->select($sql, $bindings);
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        yield from $this->inner->cursor($sql, $bindings);
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $affected = $this->inner->statement($sql, $bindings);

        if ($this->failAfterReservation && str_starts_with($sql, 'UPDATE')) {
            throw new \RuntimeException('simulated connection loss');
        }

        return $affected;
    }

    public function beginTransaction(): void
    {
        $this->inner->beginTransaction();
    }

    public function commit(): void
    {
        $this->inner->commit();
    }

    public function rollBack(): void
    {
        $this->inner->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->inner->inTransaction();
    }

    public function transaction(callable $callback): mixed
    {
        return $this->inner->transaction(fn(): mixed => $callback($this));
    }

    public function lastInsertId(): int|string|null
    {
        return $this->inner->lastInsertId();
    }

    public function affectedRows(): int
    {
        return $this->inner->affectedRows();
    }

    public function reconnect(): void
    {
        $this->inner->reconnect();
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function serverVersion(): string
    {
        return $this->inner->serverVersion();
    }

    public function escapeString(string $value): string
    {
        return $this->inner->escapeString($value);
    }
}
