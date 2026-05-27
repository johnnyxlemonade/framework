<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use RuntimeException;

final class NativeSessionStorage implements SessionStorageInterface
{
    public function __construct(
        private readonly string $cookieName = 'LEMONADE_SESSION',
        private readonly int $lifetimeSeconds = 7200,
        private readonly ?string $savePath = null,
    ) {}

    public function start(): void
    {
        if ($this->started()) {
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf(
                'Cannot start session because headers were already sent in %s on line %d.',
                $file,
                $line,
            ));
        }

        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('Cannot start native PHP session because session status is not none.');
        }

        if ($this->savePath !== null && $this->savePath !== '') {
            $this->ensureDirectoryExists($this->savePath);
            $this->ensureDirectoryIsWritable($this->savePath);

            session_save_path($this->savePath);
        }

        session_name($this->cookieName);

        session_set_cookie_params([
            'lifetime' => $this->lifetimeSeconds,
            'path' => '/',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (session_start() === false) {
            throw new RuntimeException('Failed to start native PHP session.');
        }
    }

    public function started(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();

        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();

        unset($_SESSION[$key]);
    }

    public function clear(): void
    {
        $this->start();

        $_SESSION = [];
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();

        if (session_regenerate_id($deleteOldSession) === false) {
            throw new RuntimeException('Failed to regenerate native PHP session ID.');
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf(
                'Failed to create native session directory "%s".',
                $directory,
            ));
        }
    }

    private function ensureDirectoryIsWritable(string $directory): void
    {
        if (is_writable($directory)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Native session directory "%s" is not writable.',
            $directory,
        ));
    }

    private function isSecureRequest(): bool
    {
        $serverPort = $_SERVER['SERVER_PORT'] ?? null;
        $isHttpsPort = is_int($serverPort)
            ? $serverPort === 443
            : (is_string($serverPort) && $serverPort === '443');

        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
            || $isHttpsPort;
    }
}
