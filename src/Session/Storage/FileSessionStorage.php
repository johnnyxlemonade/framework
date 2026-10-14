<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use RuntimeException;

/**
 * Persists a request-scoped session payload in one serialized file per identifier
 */
final class FileSessionStorage implements SessionStorageInterface
{
    private const string COOKIE_NAME = 'LEMONADE_SESSION';

    private bool $started = false;

    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Defines the writable directory, retention period and transport cookie name
     */
    public function __construct(
        private readonly string $directory,
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = self::COOKIE_NAME,
    ) {
    }

    /**
     * Loads or creates the file-backed session and refreshes its transport cookie
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->ensureDirectoryExists();

        $this->sessionId = $this->resolveSessionId();
        $this->data = $this->readSessionFile($this->sessionId);

        $this->sendCookie();

        $this->started = true;
    }

    /**
     * Reports whether the file-backed session has been loaded for this request
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
     * Updates a value and immediately writes the serialized payload to disk
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $this->data[$key] = $value;
        $this->writeSessionFile();
    }

    /**
     * Removes a value and immediately writes the serialized payload to disk
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);

        $this->writeSessionFile();
    }

    /**
     * Replaces the active payload with an empty persisted session
     */
    public function clear(): void
    {
        $this->start();

        $this->data = [];

        $this->writeSessionFile();
    }

    /**
     * Assigns a fresh identifier and optionally removes the former session file
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();

        $oldSessionId = $this->sessionId;
        $this->sessionId = $this->generateSessionId();

        $this->writeSessionFile();
        $this->sendCookie();

        if ($deleteOldSession) {
            $oldFile = $this->filePath($oldSessionId);

            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }
    }

    /**
     * Creates the configured storage directory before session files are accessed
     */
    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf(
                'Failed to create session directory "%s".',
                $this->directory,
            ));
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
     * Reads a non-expired serialized payload and retains only string-keyed values
     *
     * @return array<string, mixed>
     */
    private function readSessionFile(string $sessionId): array
    {
        $file = $this->filePath($sessionId);

        if (!is_file($file)) {
            return [];
        }

        if ($this->isExpired($file)) {
            unlink($file);

            return [];
        }

        $contents = file_get_contents($file);

        if ($contents === false || $contents === '') {
            return [];
        }

        $data = unserialize($contents, ['allowed_classes' => false]);

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
     * Serializes the current payload under the active session identifier
     */
    private function writeSessionFile(): void
    {
        $file = $this->filePath($this->sessionId);

        $result = file_put_contents(
            $file,
            serialize($this->data),
            LOCK_EX,
        );

        if ($result === false) {
            throw new RuntimeException(sprintf(
                'Failed to write session file "%s".',
                $file,
            ));
        }
    }

    /**
     * Builds the file name for a validated session identifier
     */
    private function filePath(string $sessionId): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'sess_' . $sessionId;
    }

    /**
     * Compares the session file modification time with the configured retention period
     */
    private function isExpired(string $file): bool
    {
        $modifiedAt = filemtime($file);

        if ($modifiedAt === false) {
            return true;
        }

        return $modifiedAt + $this->lifetimeSeconds < time();
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
