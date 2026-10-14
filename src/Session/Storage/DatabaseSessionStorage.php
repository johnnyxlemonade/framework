<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use RuntimeException;

/**
 * Persists a request-scoped session payload in a database table with expiry metadata
 */
final class DatabaseSessionStorage implements SessionStorageInterface
{
    private bool $started = false;

    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Defines the connection, table, retention period and transport cookie name
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'sessions',
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = 'LEMONADE_SESSION',
    ) {
    }

    /**
     * Loads or creates the database-backed session and refreshes its expiry metadata
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->sessionId = $this->resolveSessionId();
        $this->cleanupExpired();
        $this->data = $this->readSession($this->sessionId);
        $this->touchSession();
        $this->sendCookie();

        $this->started = true;
    }

    /**
     * Reports whether the database-backed session has been loaded for this request
     */
    public function started(): bool
    {
        return $this->started;
    }

    /**
     * Checks the in-memory session payload for the key
     */
    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->data);
    }

    /**
     * Reads a value from the in-memory payload or returns the supplied fallback
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $this->data[$key] ?? $default;
    }

    /**
     * Updates a value and immediately writes the serialized payload to the table
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $this->data[$key] = $value;
        $this->writeSession();
    }

    /**
     * Removes a value and immediately writes the serialized payload to the table
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);
        $this->writeSession();
    }

    /**
     * Replaces the active payload with an empty persisted session
     */
    public function clear(): void
    {
        $this->start();

        $this->data = [];
        $this->writeSession();
    }

    /**
     * Assigns a fresh identifier and optionally deletes the former database record
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();

        $oldSessionId = $this->sessionId;
        $this->sessionId = $this->generateSessionId();
        $this->writeSession();
        $this->sendCookie();

        if ($deleteOldSession) {
            $this->deleteSession($oldSessionId);
        }
    }

    /**
     * Accepts only framework-generated session identifiers from the request cookie
     */
    private function resolveSessionId(): string
    {
        $sessionId = $_COOKIE[$this->cookieName] ?? null;

        if (is_string($sessionId) && preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1) {
            return $sessionId;
        }

        return $this->generateSessionId();
    }

    /**
     * Generates a cryptographically random session identifier
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Loads a non-expired row and retains only string-keyed serialized values
     *
     * @return array<string, mixed>
     */
    private function readSession(string $sessionId): array
    {
        $table = $this->tableName();
        $rows = $this->connection->select(
            "SELECT payload, expires_at FROM {$table} WHERE session_id = ? LIMIT 1",
            [$sessionId],
        );

        if ($rows === []) {
            return [];
        }

        $row = $rows[0];
        $expiresAtRaw = $row['expires_at'] ?? 0;
        $expiresAt = is_int($expiresAtRaw)
            ? $expiresAtRaw
            : ((is_float($expiresAtRaw) || (is_string($expiresAtRaw) && is_numeric($expiresAtRaw))) ? (int) $expiresAtRaw : 0);

        if ($expiresAt !== 0 && $expiresAt < time()) {
            $this->deleteSession($sessionId);

            return [];
        }

        $payload = $row['payload'] ?? null;

        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $data = unserialize($payload, ['allowed_classes' => false]);

        if (!is_array($data)) {
            return [];
        }

        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Upserts the current payload together with a refreshed storage deadline
     */
    private function writeSession(): void
    {
        $table = $this->tableName();
        $now = time();
        $expiresAt = $now + $this->lifetimeSeconds;
        $payload = serialize($this->data);

        $updated = $this->connection->statement(
            "UPDATE {$table} SET payload = ?, updated_at = ?, expires_at = ? WHERE session_id = ?",
            [$payload, $now, $expiresAt, $this->sessionId],
        );

        if ($updated > 0) {
            return;
        }

        $this->connection->statement(
            "INSERT INTO {$table} (session_id, payload, updated_at, expires_at) VALUES (?, ?, ?, ?)",
            [$this->sessionId, $payload, $now, $expiresAt],
        );
    }

    /**
     * Refreshes the expiry deadline without changing the loaded payload
     */
    private function touchSession(): void
    {
        $table = $this->tableName();
        $now = time();
        $expiresAt = $now + $this->lifetimeSeconds;

        $updated = $this->connection->statement(
            "UPDATE {$table} SET updated_at = ?, expires_at = ? WHERE session_id = ?",
            [$now, $expiresAt, $this->sessionId],
        );

        if ($updated > 0) {
            return;
        }

        $this->connection->statement(
            "INSERT INTO {$table} (session_id, payload, updated_at, expires_at) VALUES (?, ?, ?, ?)",
            [$this->sessionId, serialize($this->data), $now, $expiresAt],
        );
    }

    /**
     * Deletes one session record by its validated identifier
     */
    private function deleteSession(string $sessionId): void
    {
        $table = $this->tableName();

        $this->connection->statement(
            "DELETE FROM {$table} WHERE session_id = ?",
            [$sessionId],
        );
    }

    /**
     * Removes expired session rows before the active session is loaded
     */
    private function cleanupExpired(): void
    {
        $table = $this->tableName();

        $this->connection->statement(
            "DELETE FROM {$table} WHERE expires_at < ?",
            [time()],
        );
    }

    /**
     * Validates the configured identifier before interpolating it into SQL
     */
    private function tableName(): string
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $this->table) !== 1) {
            throw new RuntimeException(sprintf(
                'Invalid session table name "%s".',
                $this->table,
            ));
        }

        return $this->table;
    }

    /**
     * Sends the active identifier with the storage retention period when headers remain mutable
     */
    private function sendCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($this->cookieName, $this->sessionId, [
            'expires' => time() + $this->lifetimeSeconds,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
