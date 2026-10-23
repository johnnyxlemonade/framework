<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Transport;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Queue\Exception\InvalidQueuePayloadException;
use Lemonade\Framework\Queue\Exception\QueueLeaseLostException;
use Lemonade\Framework\Queue\Internal\DatabaseQueueDialect;
use Lemonade\Framework\Queue\Internal\DatabaseQueueSchemaValidator;
use Lemonade\Framework\Queue\MessageSerializer;
use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\QueueReservationReceipt;
use Lemonade\Framework\Queue\RenewableQueueTransportInterface;

/**
 * Persists authenticated queue envelopes and fences each database delivery with an expiring owner token.
 *
 * @internal Applications should dispatch and process messages through QueueBusInterface.
 */
final readonly class DatabaseQueueTransport implements RenewableQueueTransportInterface
{
    private const int MAX_ERROR_BYTES = 2_048;
    private const int MAX_CLAIM_ATTEMPTS = 16;

    /**
     * Creates a transport whose claim, failure, and renewal decisions use authoritative database time.
     */
    public function __construct(
        private DatabaseDriverInterface $db,
        private MessageSerializer $serializer,
        private ConnectionInterface $connection,
        private DatabaseQueueDialect $dialect,
        DatabaseQueueSchemaValidator $schemaValidator,
        private string $table = 'system_queue_job',
        private string $failedTable = 'system_queue_failed_job',
        private int $leaseSeconds = 300,
        private int $maxAttempts = 3,
    ) {
        if ($this->leaseSeconds < 1) {
            throw new \InvalidArgumentException('Database queue lease must be at least one second.');
        }

        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('Database queue max attempts must be at least one.');
        }

        $schemaValidator->assertCompatible($this->table, $this->failedTable);
    }

    /**
     * Persists a registered message with availability derived exclusively from database time.
     */
    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void
    {
        $now = $this->nowExpression();
        $payload = $this->serializer->encode($message->message());
        $delaySeconds = max(0, $delaySeconds);

        $inserted = $this->connection->statement(
            sprintf(
                'INSERT INTO %s '
                . '(queue_name, payload, attempts, available_at, reserved_at, reservation_token, reserved_until, created_at, updated_at) '
                . 'VALUES (?, ?, 0, %s + ?, NULL, NULL, NULL, %s, %s)',
                $this->tableName($this->table),
                $now,
                $now,
                $now,
            ),
            [$message->queue(), $payload, $delaySeconds],
        );

        if ($inserted !== 1) {
            throw new \RuntimeException('Queue job could not be persisted.');
        }
    }

    /**
     * Claims one ready or expired job through a bounded optimistic compare-and-set loop.
     */
    public function dequeue(string $queue): ?QueuedMessage
    {
        for ($claimAttempt = 0; $claimAttempt < self::MAX_CLAIM_ATTEMPTS; $claimAttempt++) {
            $candidate = $this->candidate($queue);
            if ($candidate === null) {
                return null;
            }

            $result = $this->connection->transaction(
                fn(): QueuedMessage|bool|null => $this->claimCandidate(
                    queue: $queue,
                    id: $candidate['id'],
                    attempts: $candidate['attempts'],
                ),
            );

            if ($result instanceof QueuedMessage) {
                return $result;
            }

            // Null loses the compare-and-set race; true quarantines an exhausted or poison job.
        }

        return null;
    }

    /**
     * Deletes a completed job only while this delivery still owns a live database lease.
     */
    public function ack(QueuedMessage $message): void
    {
        [$id, $token] = $this->deliveryIdentity($message);
        $deleted = $this->deleteOwnedJob(
            id: $id,
            queue: $message->queue(),
            token: $token,
        );

        if ($deleted !== 1) {
            throw QueueLeaseLostException::forDelivery();
        }
    }

    /**
     * Atomically quarantines a failed delivery only while its reservation remains live and owned.
     */
    public function fail(QueuedMessage $message, string $error): void
    {
        [$id, $token] = $this->deliveryIdentity($message);
        $this->connection->transaction(function () use ($id, $message, $error, $token): void {
            $claimed = $this->claimedRow($id, $message->queue(), $token);
            $this->moveToFailed(
                id: $id,
                queue: $message->queue(),
                payload: $claimed['payload'],
                attempts: $claimed['attempts'],
                error: $error,
                token: $token,
            );
        });
    }

    /**
     * Extends a live owned lease without reviving an expired or replaced reservation.
     */
    public function renew(QueuedMessage $message): int
    {
        [$id, $token] = $this->deliveryIdentity($message);
        $now = $this->nowExpression();
        $updated = $this->connection->statement(
            sprintf(
                'UPDATE %s SET reserved_until = CASE '
                . 'WHEN reserved_until >= %s + ? THEN reserved_until + 1 ELSE %s + ? END, updated_at = %s '
                . 'WHERE id = ? AND queue_name = ? AND reservation_token = ? '
                . 'AND reserved_at IS NOT NULL AND reserved_until IS NOT NULL AND reserved_until > %s',
                $this->tableName($this->table),
                $now,
                $now,
                $now,
                $now,
            ),
            [
                $this->leaseSeconds,
                $this->leaseSeconds,
                $id,
                $message->queue(),
                $token,
            ],
        );

        if ($updated !== 1) {
            throw QueueLeaseLostException::forDelivery();
        }

        $rows = $this->connection->select(
            sprintf(
                'SELECT reserved_until FROM %s WHERE id = ? AND queue_name = ? AND reservation_token = ?',
                $this->tableName($this->table),
            ),
            [$id, $message->queue(), $token],
        );
        $reservedUntil = $this->positiveInteger($rows[0]['reserved_until'] ?? null);
        if ($reservedUntil === null) {
            throw QueueLeaseLostException::forDelivery();
        }

        return $reservedUntil;
    }

    /**
     * @return array{id:int, attempts:int}|null
     */
    private function candidate(string $queue): ?array
    {
        $now = $this->nowExpression();
        $rows = $this->connection->select(
            sprintf(
                'SELECT id, attempts FROM %s WHERE queue_name = ? AND available_at <= %s '
                . 'AND %s ORDER BY id ASC LIMIT 1',
                $this->tableName($this->table),
                $now,
                $this->eligibleLeasePredicate($now),
            ),
            [$queue],
        );
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }

        $id = $this->positiveInteger($row['id'] ?? null);
        $attempts = $this->nonNegativeInteger($row['attempts'] ?? null);
        if ($id === null || $attempts === null) {
            throw new \RuntimeException('Queue candidate contains invalid reservation metadata.');
        }

        return [
            'id' => $id,
            'attempts' => $attempts,
        ];
    }

    private function claimCandidate(string $queue, int $id, int $attempts): QueuedMessage|bool|null
    {
        $token = bin2hex(random_bytes(32));

        if ($attempts >= $this->maxAttempts) {
            return $this->quarantineExhaustedCandidate(
                id: $id,
                queue: $queue,
                attempts: $attempts,
                token: $token,
            );
        }

        $now = $this->nowExpression();
        $updated = $this->connection->statement(
            sprintf(
                'UPDATE %s SET reservation_token = ?, reserved_at = %s, reserved_until = %s + ?, '
                . 'attempts = attempts + 1, updated_at = %s '
                . 'WHERE id = ? AND queue_name = ? AND available_at <= %s AND attempts = ? AND %s',
                $this->tableName($this->table),
                $now,
                $now,
                $now,
                $now,
                $this->eligibleLeasePredicate($now),
            ),
            [$token, $this->leaseSeconds, $id, $queue, $attempts],
        );

        if ($updated === 0) {
            return null;
        }
        if ($updated !== 1) {
            throw new \RuntimeException('Queue reservation changed an unexpected number of rows.');
        }

        $claimed = $this->claimedRow($id, $queue, $token);
        $payload = $claimed['payload'];
        $claimedAttempts = $claimed['attempts'];

        try {
            $message = $this->serializer->decode($payload);
        } catch (InvalidQueuePayloadException $exception) {
            $this->moveToFailed(
                id: $id,
                queue: $queue,
                payload: $payload,
                attempts: $claimedAttempts,
                error: $exception->getMessage(),
                token: $token,
            );

            return true;
        }

        return new QueuedMessage(
            message: $message,
            queue: $queue,
            id: $id,
            attempts: $claimedAttempts,
            rawPayload: $payload,
            reservationReceipt: new QueueReservationReceipt($token),
        );
    }

    private function quarantineExhaustedCandidate(
        int $id,
        string $queue,
        int $attempts,
        string $token,
    ): bool|null {
        $now = $this->nowExpression();
        $updated = $this->connection->statement(
            sprintf(
                'UPDATE %s SET reservation_token = ?, reserved_at = %s, reserved_until = %s + ?, updated_at = %s '
                . 'WHERE id = ? AND queue_name = ? AND available_at <= %s AND attempts = ? AND %s',
                $this->tableName($this->table),
                $now,
                $now,
                $now,
                $now,
                $this->eligibleLeasePredicate($now),
            ),
            [$token, $this->leaseSeconds, $id, $queue, $attempts],
        );

        if ($updated === 0) {
            return null;
        }
        if ($updated !== 1) {
            throw new \RuntimeException('Queue exhaustion claim changed an unexpected number of rows.');
        }

        $claimed = $this->claimedRow($id, $queue, $token);
        $this->moveToFailed(
            id: $id,
            queue: $queue,
            payload: $claimed['payload'],
            attempts: $claimed['attempts'],
            error: sprintf(
                'Queue lease expired without acknowledgement; maximum attempts (%d) exhausted.',
                $this->maxAttempts,
            ),
            token: $token,
        );

        return true;
    }

    /**
     * @return array{payload:string, attempts:int}
     */
    private function claimedRow(int $id, string $queue, string $token): array
    {
        $now = $this->nowExpression();
        $rows = $this->connection->select(
            sprintf(
                'SELECT payload, attempts FROM %s WHERE id = ? AND queue_name = ? AND reservation_token = ? '
                . 'AND reserved_at IS NOT NULL AND reserved_until IS NOT NULL AND reserved_until > %s',
                $this->tableName($this->table),
                $now,
            ),
            [$id, $queue, $token],
        );
        $row = $rows[0] ?? null;
        if ($row === null) {
            throw QueueLeaseLostException::forDelivery();
        }

        $payloadRaw = $row['payload'] ?? null;
        $attempts = $this->positiveInteger($row['attempts'] ?? null);
        if ((!is_scalar($payloadRaw) && !$payloadRaw instanceof \Stringable) || $attempts === null) {
            throw new \RuntimeException('Claimed queue job contains invalid persisted data.');
        }

        return [
            'payload' => (string) $payloadRaw,
            'attempts' => $attempts,
        ];
    }

    private function moveToFailed(
        int $id,
        string $queue,
        string $payload,
        int $attempts,
        string $error,
        string $token,
    ): void {
        $this->connection->transaction(function () use (
            $id,
            $queue,
            $payload,
            $attempts,
            $error,
            $token,
        ): void {
            $this->insertFailed(
                id: $id,
                queue: $queue,
                payload: $payload,
                attempts: $attempts,
                error: $error,
            );

            $deleted = $this->deleteOwnedJob(
                id: $id,
                queue: $queue,
                token: $token,
            );
            if ($deleted !== 1) {
                throw QueueLeaseLostException::forDelivery();
            }
        });
    }

    private function insertFailed(
        int $id,
        string $queue,
        string $payload,
        int $attempts,
        string $error,
    ): void {
        $inserted = $this->connection->statement(
            sprintf(
                'INSERT INTO %s (job_id, queue_name, payload, attempts, error_message, failed_at) '
                . 'VALUES (?, ?, ?, ?, ?, %s)',
                $this->tableName($this->failedTable),
                $this->nowExpression(),
            ),
            [
                $id,
                $queue,
                $payload,
                $attempts,
                substr($error, 0, self::MAX_ERROR_BYTES),
            ],
        );

        if ($inserted !== 1) {
            throw new \RuntimeException('Failed queue job could not be persisted.');
        }
    }

    private function deleteOwnedJob(int $id, string $queue, string $token): int
    {
        return $this->connection->statement(
            sprintf(
                'DELETE FROM %s WHERE id = ? AND queue_name = ? AND reservation_token = ? '
                . 'AND reserved_at IS NOT NULL AND reserved_until IS NOT NULL AND reserved_until > %s',
                $this->tableName($this->table),
                $this->nowExpression(),
            ),
            [$id, $queue, $token],
        );
    }

    /**
     * @return array{int, string}
     */
    private function deliveryIdentity(QueuedMessage $message): array
    {
        $id = $message->id();
        $receipt = $message->reservationReceipt();
        if ($id === null || !$receipt instanceof QueueReservationReceipt) {
            throw QueueLeaseLostException::forDelivery();
        }

        return [$id, $receipt->ownerToken()];
    }

    private function eligibleLeasePredicate(string $now): string
    {
        return sprintf(
            '((reserved_at IS NULL AND reservation_token IS NULL AND reserved_until IS NULL) '
            . 'OR (reserved_at IS NOT NULL AND reservation_token IS NOT NULL '
            . 'AND reserved_until IS NOT NULL AND reserved_until <= %s))',
            $now,
        );
    }

    private function nowExpression(): string
    {
        return $this->dialect->currentEpochExpression();
    }

    private function positiveInteger(mixed $value): ?int
    {
        $integer = $this->nonNegativeInteger($value);

        return $integer !== null && $integer > 0 ? $integer : null;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            $integer = (int) $value;

            return $integer >= 0 ? $integer : null;
        }

        return null;
    }

    private function tableName(string $table): string
    {
        return $this->db->protect_identifiers($table, true, null, false);
    }
}
