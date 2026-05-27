<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Transport;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Queue\MessageSerializer;
use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\QueueTransportInterface;

final class DatabaseQueueTransport implements QueueTransportInterface
{
    public function __construct(
        private readonly DatabaseDriverInterface $db,
        private readonly MessageSerializer $serializer,
        private readonly string $table = 'system_queue_job',
        private readonly string $failedTable = 'system_queue_failed_job',
    ) {}

    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void
    {
        $now = time();
        $availableAt = $now + max(0, $delaySeconds);
        $payload = $this->serializer->encode($message->message());

        $this->db->query(
            sprintf(
                'INSERT INTO %s (queue_name, payload, attempts, available_at, reserved_at, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, ?, ?)',
                $this->tableName($this->table),
            ),
            [$message->queue(), $payload, 0, $availableAt, $now, $now],
        );
    }

    public function dequeue(string $queue): ?QueuedMessage
    {
        $now = time();
        $result = $this->db->query(
            sprintf(
                'SELECT id, payload, attempts FROM %s WHERE queue_name = ? AND available_at <= ? AND reserved_at IS NULL ORDER BY id ASC LIMIT 1',
                $this->tableName($this->table),
            ),
            [$queue, $now],
        );

        if (!$result instanceof DatabaseResultInterface) {
            return null;
        }

        $row = $result->row_array();
        if (!is_array($row)) {
            return null;
        }

        $idRaw = $row['id'] ?? 0;
        $id = is_int($idRaw) ? $idRaw : ((is_float($idRaw) || (is_string($idRaw) && is_numeric($idRaw))) ? (int) $idRaw : 0);
        if ($id <= 0) {
            return null;
        }

        $attemptsRaw = $row['attempts'] ?? 0;
        $attemptsBase = is_int($attemptsRaw) ? $attemptsRaw : ((is_float($attemptsRaw) || (is_string($attemptsRaw) && is_numeric($attemptsRaw))) ? (int) $attemptsRaw : 0);
        $attempts = $attemptsBase + 1;
        $updated = $this->db->query(
            sprintf(
                'UPDATE %s SET reserved_at = ?, attempts = ?, updated_at = ? WHERE id = ? AND reserved_at IS NULL',
                $this->tableName($this->table),
            ),
            [$now, $attempts, $now, $id],
        );

        if ($updated === false || $this->db->affected_rows() === 0) {
            return null;
        }

        $payloadRaw = $row['payload'] ?? '';
        $payload = is_scalar($payloadRaw) || $payloadRaw instanceof \Stringable ? (string) $payloadRaw : '';
        $message = $this->serializer->decode($payload);

        return new QueuedMessage(
            message: $message,
            queue: $queue,
            id: $id,
            attempts: $attempts,
        );
    }

    public function ack(QueuedMessage $message): void
    {
        $id = $message->id();
        if ($id === null) {
            return;
        }

        $this->db->query(
            sprintf('DELETE FROM %s WHERE id = ?', $this->tableName($this->table)),
            [$id],
        );
    }

    public function fail(QueuedMessage $message, string $error): void
    {
        $id = $message->id();
        $payload = $this->serializer->encode($message->message());
        $now = time();

        $this->db->query(
            sprintf(
                'INSERT INTO %s (job_id, queue_name, payload, attempts, error_message, failed_at) VALUES (?, ?, ?, ?, ?, ?)',
                $this->tableName($this->failedTable),
            ),
            [$id, $message->queue(), $payload, $message->attempts(), $error, $now],
        );

        if ($id !== null) {
            $this->db->query(
                sprintf('DELETE FROM %s WHERE id = ?', $this->tableName($this->table)),
                [$id],
            );
        }
    }

    private function tableName(string $table): string
    {
        return $this->db->protect_identifiers($table, true, null, false);
    }
}
