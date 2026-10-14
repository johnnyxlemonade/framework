<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use RuntimeException;

/**
 * Adapts PHP's native session runtime to the framework storage contract
 */
final readonly class NativeSessionStorage implements SessionStorageInterface
{
    /**
     * Defines the cookie identity, shared retention period and optional PHP session save path
     */
    public function __construct(
        private string $cookieName = 'LEMONADE_SESSION',
        private int $lifetimeSeconds = 7200,
        private ?string $savePath = null,
    ) {
    }

    /**
     * Configures native PHP session settings and starts the active session
     */
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

        ini_set('session.gc_maxlifetime', (string) $this->lifetimeSeconds);

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

    /**
     * Reports PHP's process-level native session initialization state
     */
    public function started(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Checks the native session payload for the key
     */
    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Reads a native session value or returns the supplied fallback
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Stores a value in the native session payload
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $_SESSION[$key] = $value;
    }

    /**
     * Removes a key from the native session payload
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($_SESSION[$key]);
    }

    /**
     * Clears every value from the active native session payload
     */
    public function clear(): void
    {
        $this->start();

        $_SESSION = [];
    }

    /**
     * Rotates the native session identifier and can delete the prior payload
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();

        if (session_regenerate_id($deleteOldSession) === false) {
            throw new RuntimeException('Failed to regenerate native PHP session ID.');
        }
    }

    /**
     * Creates the configured save directory before PHP attempts to write session data
     */
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

    /**
     * Rejects a configured save directory that cannot persist native session data
     */
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

    /**
     * Detects HTTPS from PHP request globals for the Secure cookie attribute
     */
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
