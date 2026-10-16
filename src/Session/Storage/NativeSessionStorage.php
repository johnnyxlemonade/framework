<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use RuntimeException;

/**
 * Adapts PHP's native session runtime with framework-owned cookie and garbage-collection policy.
 *
 * The active PHP save handler owns persistence and timestamp renewal when the session is closed at
 * traditional request shutdown; gc_maxlifetime supplies a GC threshold, not read-time expiration.
 */
final readonly class NativeSessionStorage implements SessionStorageInterface
{
    private SessionCookie $cookie;
    private SessionGarbageCollectionPolicy $garbageCollectionPolicy;

    /**
     * Defines the native identity, sliding retention, save path, and request-driven GC probability.
     */
    public function __construct(
        private string $cookieName = 'LEMONADE_SESSION',
        private int $lifetimeSeconds = 7200,
        private ?string $savePath = null,
        ?SessionCookie $cookie = null,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
    ) {
        $this->cookie = $cookie ?? new SessionCookie($cookieName, $lifetimeSeconds);
        $this->garbageCollectionPolicy = $garbageCollectionPolicy ?? new SessionGarbageCollectionPolicy();
    }

    /**
     * Starts or resumes the PHP session and refreshes both native storage and browser retention.
     */
    public function start(): void
    {
        if ($this->started()) {
            return;
        }

        $sessionStatus = session_status();

        if ($sessionStatus === PHP_SESSION_ACTIVE) {
            throw new RuntimeException(sprintf(
                'Cannot start native PHP session "%s" because session "%s" is already active.',
                $this->cookieName,
                session_name(),
            ));
        }

        if ($sessionStatus !== PHP_SESSION_NONE) {
            throw new RuntimeException('Cannot start native PHP session because session status is not none.');
        }

        $this->cookie->assertCanChange();
        $this->configureRuntime();

        if (session_start() === false) {
            throw new RuntimeException('Failed to start native PHP session.');
        }

        $this->cookie->send($this->activeSessionId());
    }

    /**
     * Reports whether this cookie identity owns the active native PHP session.
     */
    public function started(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE
            && session_name() === $this->cookieName;
    }

    /**
     * Checks the active native payload for a key.
     */
    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Returns a native session value or the supplied fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return array_key_exists($key, $_SESSION)
            ? $_SESSION[$key]
            : $default;
    }

    /**
     * Stores a value for persistence when PHP closes the active session.
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $_SESSION[$key] = $value;
    }

    /**
     * Removes one value for persistence when PHP closes the active session.
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($_SESSION[$key]);
    }

    /**
     * Clears the payload while preserving the active identifier and cookie.
     */
    public function clear(): void
    {
        $this->start();

        $_SESSION = [];
    }

    /**
     * Deletes the native record and cookie so later access receives a fresh identity.
     */
    public function destroy(): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        $_SESSION = [];

        if (session_destroy() === false) {
            throw new RuntimeException('Failed to destroy native PHP session.');
        }

        $_SESSION = [];
        $this->cookie->expire();
    }

    /**
     * Rotates the native identifier, preserves its payload, and optionally deletes the old record.
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        if (session_regenerate_id($deleteOldSession) === false) {
            throw new RuntimeException('Failed to regenerate native PHP session ID.');
        }

        $this->cookie->send($this->activeSessionId());
    }

    private function configureRuntime(): void
    {
        if ($this->savePath !== null && $this->savePath !== '') {
            $this->ensureDirectoryExists($this->savePath);
            $this->ensureDirectoryIsWritable($this->savePath);

            if (session_save_path($this->savePath) === false) {
                throw new RuntimeException(sprintf(
                    'Failed to configure native session directory "%s".',
                    $this->savePath,
                ));
            }
        }

        $this->setIniDirective('session.gc_maxlifetime', (string) $this->lifetimeSeconds);
        $this->setIniDirective(
            'session.gc_probability',
            (string) $this->garbageCollectionPolicy->probability(),
        );
        $this->setIniDirective(
            'session.gc_divisor',
            (string) $this->garbageCollectionPolicy->divisor(),
        );
        $this->setIniDirective('session.use_strict_mode', '1');
        $this->setIniDirective('session.use_cookies', '1');
        $this->setIniDirective('session.use_only_cookies', '1');

        if (session_name($this->cookieName) === false) {
            throw new RuntimeException(sprintf(
                'Failed to configure native session name "%s".',
                $this->cookieName,
            ));
        }

        if (session_set_cookie_params($this->cookie->nativeParameters()) === false) {
            throw new RuntimeException('Failed to configure native session cookie parameters.');
        }
    }

    private function activeSessionId(): string
    {
        $sessionId = session_id();

        if (is_string($sessionId) && $sessionId !== '') {
            return $sessionId;
        }

        throw new RuntimeException('Native PHP session did not provide an active identifier.');
    }

    private function setIniDirective(string $name, string $value): void
    {
        if (ini_set($name, $value) !== false) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Failed to configure PHP directive "%s" for native sessions.',
            $name,
        ));
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf(
                'Failed to create native session directory "%s".',
                $directory,
            ));
        }

        if (!chmod($directory, 0700)) {
            throw new RuntimeException(sprintf(
                'Failed to secure native session directory "%s".',
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
}
