<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use RuntimeException;

final class DatabaseSessionStorage implements SessionStorageInterface
{
    private bool $started = false;

    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'sessions',
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = 'LEMONADE_SESSION',
    ) {}

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

    public function started(): bool
    {
        return $this->started;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();

        $this->data[$key] = $value;
        $this->writeSession();
    }

    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);
        $this->writeSession();
    }

    public function clear(): void
    {
        $this->start();

        $this->data = [];
        $this->writeSession();
    }

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

    private function resolveSessionId(): string
    {
        $sessionId = $_COOKIE[$this->cookieName] ?? null;

        if (is_string($sessionId) && preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1) {
            return $sessionId;
        }

        return $this->generateSessionId();
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
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

    private function deleteSession(string $sessionId): void
    {
        $table = $this->tableName();

        $this->connection->statement(
            "DELETE FROM {$table} WHERE session_id = ?",
            [$sessionId],
        );
    }

    private function cleanupExpired(): void
    {
        $table = $this->tableName();

        $this->connection->statement(
            "DELETE FROM {$table} WHERE expires_at < ?",
            [time()],
        );
    }

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
