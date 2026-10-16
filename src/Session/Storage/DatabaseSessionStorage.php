<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use InvalidArgumentException;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use Lemonade\Framework\Session\SessionIdGenerator;
use RuntimeException;
use Throwable;

/**
 * Persists sliding sessions in a consumer-owned table with bounded opportunistic cleanup.
 *
 * Concurrent payload mutations use last-writer-wins semantics.
 */
final class DatabaseSessionStorage implements SessionStorageInterface
{
    private const int DEFAULT_GC_BATCH_SIZE = 100;

    private bool $started = false;
    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private readonly SessionCookie $cookie;
    private readonly SessionGarbageCollectionPolicy $garbageCollectionPolicy;
    private readonly SessionIdGenerator $sessionIdGenerator;

    /**
     * Defines the table, sliding retention, browser identity, and bounded request GC policy.
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'sessions',
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = 'LEMONADE_SESSION',
        private readonly int $gcBatchSize = self::DEFAULT_GC_BATCH_SIZE,
        ?SessionCookie $cookie = null,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
    ) {
        if ($gcBatchSize < 1) {
            throw new InvalidArgumentException('Database session GC batch size must be positive.');
        }

        $this->cookie = $cookie ?? new SessionCookie($cookieName, $lifetimeSeconds);
        $this->garbageCollectionPolicy = $garbageCollectionPolicy ?? new SessionGarbageCollectionPolicy();
        $this->sessionIdGenerator = new SessionIdGenerator();
    }

    /**
     * Loads a live row or creates a fresh identity, then refreshes row and cookie expiry.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->cookie->assertCanChange();
        $this->cleanupExpiredIfSelected();

        $candidate = $this->resolveSessionId();
        $data = $candidate === null ? null : $this->readSession($candidate);

        if ($candidate === null || $data === null) {
            $this->sessionId = $this->sessionIdGenerator->generate();
            $this->data = [];
            $this->touchSession();
        } else {
            $this->sessionId = $candidate;
            $this->data = $data;

            if (!$this->touchExistingSession()) {
                $this->sessionId = $this->sessionIdGenerator->generate();
                $this->data = [];
                $this->touchSession();
            }
        }

        $this->cookie->send($this->sessionId);
        $this->started = true;
    }

    /**
     * Reports whether this request has established its database-backed identity.
     */
    public function started(): bool
    {
        return $this->started;
    }

    /**
     * Checks the payload loaded for this request.
     */
    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->data);
    }

    /**
     * Returns a loaded value or the supplied fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return array_key_exists($key, $this->data)
            ? $this->data[$key]
            : $default;
    }

    /**
     * Persists a value and renews the row expiry.
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $this->data[$key] = $value;
        $this->writeSession();
    }

    /**
     * Removes one value and renews the row expiry.
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);
        $this->writeSession();
    }

    /**
     * Persists an empty payload while retaining the active identity and cookie.
     */
    public function clear(): void
    {
        $this->start();

        $this->data = [];
        $this->writeSession();
    }

    /**
     * Deletes the active row and expires its cookie so later access starts a new identity.
     */
    public function destroy(): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        $this->deleteSession($this->sessionId);
        $this->cookie->expire();
        $this->reset();
    }

    /**
     * Copies the payload to a fresh identity and optionally invalidates the old row.
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        $oldSessionId = $this->sessionId;
        $this->sessionId = $this->sessionIdGenerator->generate();
        $this->writeSession();

        if ($deleteOldSession) {
            $this->deleteSession($oldSessionId);
        }

        $this->cookie->send($this->sessionId);
    }

    private function reset(): void
    {
        $this->started = false;
        $this->sessionId = '';
        $this->data = [];
    }

    private function resolveSessionId(): ?string
    {
        $sessionId = $_COOKIE[$this->cookieName] ?? null;

        if (is_string($sessionId) && $this->sessionIdGenerator->isValid($sessionId)) {
            return $sessionId;
        }

        return null;
    }

    /**
     * @return null|array<string, mixed>
     */
    private function readSession(string $sessionId): ?array
    {
        $table = $this->tableName();
        $rows = $this->connection->select(
            "SELECT payload, expires_at FROM {$table} WHERE session_id = ? LIMIT 1",
            [$sessionId],
        );

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];
        $expiresAt = $this->integerValue($row['expires_at'] ?? null);

        if ($expiresAt === null || $expiresAt < time()) {
            $this->deleteSession($sessionId);

            return null;
        }

        $payload = $row['payload'] ?? null;

        if (!is_string($payload) || $payload === '') {
            $this->deleteSession($sessionId);

            return null;
        }

        $data = @unserialize($payload, ['allowed_classes' => false]);

        if (!is_array($data)) {
            $this->deleteSession($sessionId);

            return null;
        }

        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function writeSession(): void
    {
        $now = time();
        $expiresAt = $now + $this->lifetimeSeconds;
        $payload = serialize($this->data);

        $this->updateOrInsert(
            updateSql: sprintf(
                'UPDATE %s SET payload = ?, updated_at = ?, expires_at = ? WHERE session_id = ?',
                $this->tableName(),
            ),
            updateBindings: [$payload, $now, $expiresAt, $this->sessionId],
            insertBindings: [$this->sessionId, $payload, $now, $expiresAt],
        );
    }

    private function touchSession(): void
    {
        $now = time();
        $expiresAt = $now + $this->lifetimeSeconds;

        $this->updateOrInsert(
            updateSql: sprintf(
                'UPDATE %s SET updated_at = ?, expires_at = ? WHERE session_id = ?',
                $this->tableName(),
            ),
            updateBindings: [$now, $expiresAt, $this->sessionId],
            insertBindings: [$this->sessionId, serialize($this->data), $now, $expiresAt],
        );
    }

    private function touchExistingSession(): bool
    {
        $now = time();
        $affectedRows = $this->connection->statement(
            sprintf(
                'UPDATE %s SET updated_at = ?, expires_at = ? WHERE session_id = ?',
                $this->tableName(),
            ),
            [$now, $now + $this->lifetimeSeconds, $this->sessionId],
        );

        return $affectedRows > 0 || $this->sessionExists($this->sessionId);
    }

    /**
     * @param list<mixed> $updateBindings
     * @param list<mixed> $insertBindings
     */
    private function updateOrInsert(
        string $updateSql,
        array $updateBindings,
        array $insertBindings,
    ): void {
        if ($this->connection->statement($updateSql, $updateBindings) > 0) {
            return;
        }
        if ($this->sessionExists($this->sessionId)) {
            return;
        }

        $insertSql = sprintf(
            'INSERT INTO %s (session_id, payload, updated_at, expires_at) VALUES (?, ?, ?, ?)',
            $this->tableName(),
        );

        try {
            $this->connection->statement($insertSql, $insertBindings);

            if (!$this->sessionExists($this->sessionId)) {
                throw new RuntimeException(sprintf(
                    'Failed to create database session "%s".',
                    $this->sessionId,
                ));
            }
        } catch (Throwable $insertFailure) {
            if (
                $this->connection->statement($updateSql, $updateBindings) > 0
                || $this->sessionExists($this->sessionId)
            ) {
                return;
            }

            throw $insertFailure;
        }
    }

    /**
     * @phpstan-impure
     */
    private function sessionExists(string $sessionId): bool
    {
        $rows = $this->connection->select(
            sprintf('SELECT session_id FROM %s WHERE session_id = ? LIMIT 1', $this->tableName()),
            [$sessionId],
        );

        return $rows !== [];
    }

    private function deleteSession(string $sessionId): void
    {
        $this->connection->statement(
            sprintf('DELETE FROM %s WHERE session_id = ?', $this->tableName()),
            [$sessionId],
        );

        if ($this->sessionExists($sessionId)) {
            throw new RuntimeException(sprintf(
                'Failed to invalidate database session "%s".',
                $sessionId,
            ));
        }
    }

    private function cleanupExpiredIfSelected(): void
    {
        if (!$this->garbageCollectionPolicy->shouldRun()) {
            return;
        }

        $table = $this->tableName();
        $now = time();
        $rows = $this->connection->select(
            "SELECT session_id FROM {$table} WHERE expires_at < ? ORDER BY expires_at ASC LIMIT {$this->gcBatchSize}",
            [$now],
        );
        $sessionIds = [];

        foreach ($rows as $row) {
            $sessionId = $row['session_id'] ?? null;

            if (is_string($sessionId) || is_int($sessionId)) {
                $sessionIds[] = $sessionId;
            }
        }

        if ($sessionIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($sessionIds), '?'));
        $bindings = [...$sessionIds, $now];

        $this->connection->statement(
            "DELETE FROM {$table} WHERE session_id IN ({$placeholders}) AND expires_at < ?",
            $bindings,
        );
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return null;
    }

    private function tableName(): string
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/D', $this->table) !== 1) {
            throw new RuntimeException(sprintf(
                'Invalid session table name "%s".',
                $this->table,
            ));
        }

        return $this->table;
    }
}
