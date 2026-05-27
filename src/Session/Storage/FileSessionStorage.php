<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use RuntimeException;

final class FileSessionStorage implements SessionStorageInterface
{
    private const COOKIE_NAME = 'LEMONADE_SESSION';

    private bool $started = false;

    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly string $directory,
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = self::COOKIE_NAME,
    ) {}

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
        $this->writeSessionFile();
    }

    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);

        $this->writeSessionFile();
    }

    public function clear(): void
    {
        $this->start();

        $this->data = [];

        $this->writeSessionFile();
    }

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

    private function filePath(string $sessionId): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'sess_' . $sessionId;
    }

    private function isExpired(string $file): bool
    {
        $modifiedAt = filemtime($file);

        if ($modifiedAt === false) {
            return true;
        }

        return $modifiedAt + $this->lifetimeSeconds < time();
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
