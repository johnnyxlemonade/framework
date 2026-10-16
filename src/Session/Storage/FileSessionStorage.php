<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use DirectoryIterator;
use InvalidArgumentException;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use Lemonade\Framework\Session\SessionIdGenerator;
use OutOfBoundsException;
use RuntimeException;

/**
 * Persists a sliding session in one private, atomically replaced file per identifier.
 *
 * Atomic replacement prevents partial reads; concurrent payload mutations remain last-writer-wins.
 */
final class FileSessionStorage implements SessionStorageInterface
{
    private const string COOKIE_NAME = 'LEMONADE_SESSION';
    private const string GC_CURSOR_FILE = '.session-gc.cursor';
    private const string TEMPORARY_FILE_PREFIX = '.session-write-';
    private const int DEFAULT_GC_BATCH_SIZE = 100;
    private const int MINIMUM_TEMPORARY_FILE_AGE_SECONDS = 300;

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
     * Defines the private directory, sliding retention, cookie, and bounded request GC policy.
     */
    public function __construct(
        private readonly string $directory,
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = self::COOKIE_NAME,
        private readonly int $gcBatchSize = self::DEFAULT_GC_BATCH_SIZE,
        ?SessionCookie $cookie = null,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
    ) {
        if ($gcBatchSize < 1) {
            throw new InvalidArgumentException('File session GC batch size must be positive.');
        }

        $this->cookie = $cookie ?? new SessionCookie($cookieName, $lifetimeSeconds);
        $this->garbageCollectionPolicy = $garbageCollectionPolicy ?? new SessionGarbageCollectionPolicy();
        $this->sessionIdGenerator = new SessionIdGenerator();
    }

    /**
     * Loads a valid file identity, refreshes its mtime, and sends a sliding cookie.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->cookie->assertCanChange();
        $this->ensureDirectoryExists();
        $this->collectGarbageIfSelected();

        $candidate = $this->resolveSessionId();
        $data = $candidate === null ? null : $this->readSessionFile($candidate);

        if ($candidate === null || $data === null) {
            $this->startNewSession();
        } else {
            $this->resumeSession($candidate, $data);
        }

        $this->cookie->send($this->sessionId);
        $this->started = true;
    }

    /**
     * Reports whether this request has established its file-backed identity.
     */
    public function started(): bool
    {
        return $this->started;
    }

    /**
     * Checks the in-memory payload loaded for this request.
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
     * Stores a value through an atomic file replacement.
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $this->data[$key] = $value;
        $this->writeSessionFile();
    }

    /**
     * Removes a value and atomically persists the remaining payload.
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);
        $this->writeSessionFile();
    }

    /**
     * Persists an empty payload while retaining the active identity and cookie.
     */
    public function clear(): void
    {
        $this->start();

        $this->data = [];
        $this->writeSessionFile();
    }

    /**
     * Removes the active file and expires its cookie so later access starts a new identity.
     */
    public function destroy(): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        $this->deleteSessionFile($this->sessionId);
        $this->cookie->expire();
        $this->reset();
    }

    /**
     * Copies the payload to a fresh identity and optionally invalidates the old file.
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        $oldSessionId = $this->sessionId;
        $this->sessionId = $this->sessionIdGenerator->generate();
        $this->writeSessionFile();

        if ($deleteOldSession) {
            $this->deleteSessionFile($oldSessionId);
        }

        $this->cookie->send($this->sessionId);
    }

    private function reset(): void
    {
        $this->started = false;
        $this->sessionId = '';
        $this->data = [];
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf(
                'Failed to create session directory "%s".',
                $this->directory,
            ));
        }

        if (!chmod($this->directory, 0700)) {
            throw new RuntimeException(sprintf(
                'Failed to secure session directory "%s".',
                $this->directory,
            ));
        }
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
    private function readSessionFile(string $sessionId): ?array
    {
        $file = $this->filePath($sessionId);

        if (is_link($file)) {
            $this->unlinkPath($file);

            return null;
        }
        if (!is_file($file)) {
            return null;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open session file "%s" for reading.', $file));
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException(sprintf('Failed to lock session file "%s" for reading.', $file));
            }

            $metadata = fstat($handle);

            if (!is_array($metadata) || !isset($metadata['mtime']) || !is_int($metadata['mtime'])) {
                throw new RuntimeException(sprintf('Failed to read metadata for session file "%s".', $file));
            }

            if ($metadata['mtime'] + $this->lifetimeSeconds < time()) {
                flock($handle, LOCK_UN);
                fclose($handle);
                $handle = null;
                $this->deleteExpiredSessionFile($file);

                return null;
            }

            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);

            if ($contents === false || $contents === '') {
                return null;
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $data = @unserialize($contents, ['allowed_classes' => false]);

        if (!is_array($data)) {
            $this->deleteSessionFile($sessionId);

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

    /**
     * @param array<string, mixed> $data
     */
    private function resumeSession(string $sessionId, array $data): void
    {
        $this->sessionId = $sessionId;
        $this->data = $data;

        if ($this->touchSessionFile($sessionId)) {
            return;
        }

        $this->startNewSession();
    }

    private function startNewSession(): void
    {
        $this->sessionId = $this->sessionIdGenerator->generate();
        $this->data = [];
        $this->writeSessionFile();
    }

    private function touchSessionFile(string $sessionId): bool
    {
        $file = $this->filePath($sessionId);

        if (!$this->isRegularSessionFile($file)) {
            return false;
        }

        if (!chmod($file, 0600)) {
            if (!$this->isRegularSessionFile($file)) {
                return false;
            }

            throw new RuntimeException(sprintf(
                'Failed to refresh session file "%s".',
                $file,
            ));
        }

        if (!touch($file)) {
            if (!$this->isRegularSessionFile($file)) {
                return false;
            }

            throw new RuntimeException(sprintf(
                'Failed to refresh session file "%s".',
                $file,
            ));
        }

        return true;
    }

    /**
     * Rechecks the file after each filesystem operation that may race with deletion.
     *
     * @phpstan-impure
     */
    private function isRegularSessionFile(string $file): bool
    {
        clearstatcache(true, $file);

        return is_file($file) && !is_link($file);
    }

    private function writeSessionFile(): void
    {
        $file = $this->filePath($this->sessionId);
        $temporaryFile = tempnam($this->directory, self::TEMPORARY_FILE_PREFIX);

        if ($temporaryFile === false) {
            throw new RuntimeException(sprintf(
                'Failed to create a temporary session file in "%s".',
                $this->directory,
            ));
        }

        try {
            if (!chmod($temporaryFile, 0600)) {
                throw new RuntimeException(sprintf('Failed to secure temporary session file "%s".', $temporaryFile));
            }

            $payload = serialize($this->data);
            $result = file_put_contents($temporaryFile, $payload, LOCK_EX);

            if ($result !== strlen($payload)) {
                throw new RuntimeException(sprintf('Failed to write temporary session file "%s".', $temporaryFile));
            }

            if (!rename($temporaryFile, $file)) {
                throw new RuntimeException(sprintf('Failed to replace session file "%s" atomically.', $file));
            }

            $temporaryFile = '';

            if (!chmod($file, 0600)) {
                throw new RuntimeException(sprintf('Failed to secure session file "%s".', $file));
            }
        } finally {
            if ($temporaryFile !== '' && (is_file($temporaryFile) || is_link($temporaryFile))) {
                @unlink($temporaryFile);
            }
        }
    }

    private function deleteSessionFile(string $sessionId): void
    {
        $file = $this->filePath($sessionId);

        if (!is_file($file) && !is_link($file)) {
            return;
        }

        $this->unlinkPath($file);
    }

    private function deleteExpiredSessionFile(string $file): void
    {
        if (is_link($file)) {
            $this->unlinkPath($file);

            return;
        }
        if (!is_file($file)) {
            return;
        }

        $modifiedAt = filemtime($file);

        if ($modifiedAt === false || $modifiedAt + $this->lifetimeSeconds >= time()) {
            return;
        }

        $this->unlinkPath($file);
    }

    private function unlinkPath(string $file): void
    {
        if (@unlink($file) || (!is_file($file) && !is_link($file))) {
            return;
        }

        throw new RuntimeException(sprintf('Failed to delete session file "%s".', $file));
    }

    private function collectGarbageIfSelected(): void
    {
        if (!$this->garbageCollectionPolicy->shouldRun()) {
            return;
        }

        $this->collectGarbageBatch();
    }

    private function collectGarbageBatch(): void
    {
        $cursorFile = $this->directory . DIRECTORY_SEPARATOR . self::GC_CURSOR_FILE;
        $cursor = fopen($cursorFile, 'c+');

        if ($cursor === false) {
            throw new RuntimeException(sprintf('Failed to open session GC cursor "%s".', $cursorFile));
        }

        try {
            if (!chmod($cursorFile, 0600)) {
                throw new RuntimeException(sprintf('Failed to secure session GC cursor "%s".', $cursorFile));
            }
            if (!flock($cursor, LOCK_EX | LOCK_NB)) {
                return;
            }

            $position = $this->readGcCursor($cursor);
            $iterator = new DirectoryIterator($this->directory);

            try {
                $iterator->seek($position);
            } catch (OutOfBoundsException) {
                $iterator->rewind();
            }

            $processed = 0;

            while ($iterator->valid() && $processed < $this->gcBatchSize) {
                $name = $iterator->getFilename();

                if (!$iterator->isDot() && $name !== self::GC_CURSOR_FILE) {
                    ++$processed;
                }
                if ($this->isSessionFileName($name)) {
                    $this->deleteExpiredSessionFile($iterator->getPathname());
                } elseif ($this->isTemporaryFileName($name)) {
                    $this->deleteAbandonedTemporaryFile($iterator->getPathname());
                }

                $iterator->next();
            }

            $nextPosition = $iterator->valid() ? (int) $iterator->key() : 0;
            $this->writeGcCursor($cursor, $nextPosition);
            flock($cursor, LOCK_UN);
        } finally {
            fclose($cursor);
        }
    }

    /**
     * @param resource $cursor
     */
    private function readGcCursor($cursor): int
    {
        rewind($cursor);
        $value = stream_get_contents($cursor);

        if (!is_string($value) || preg_match('/^[0-9]+$/D', trim($value)) !== 1) {
            return 0;
        }

        return (int) trim($value);
    }

    /**
     * @param resource $cursor
     */
    private function writeGcCursor($cursor, int $position): void
    {
        rewind($cursor);

        if (!ftruncate($cursor, 0) || fwrite($cursor, (string) $position) === false || !fflush($cursor)) {
            throw new RuntimeException('Failed to persist the session GC cursor.');
        }
    }

    private function filePath(string $sessionId): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'sess_' . $sessionId;
    }

    private function isSessionFileName(string $name): bool
    {
        return str_starts_with($name, 'sess_')
            && $this->sessionIdGenerator->isValid(substr($name, strlen('sess_')));
    }

    private function isTemporaryFileName(string $name): bool
    {
        return str_starts_with($name, self::TEMPORARY_FILE_PREFIX);
    }

    private function deleteAbandonedTemporaryFile(string $file): void
    {
        $metadata = lstat($file);

        if (!is_array($metadata) || !isset($metadata['mtime']) || !is_int($metadata['mtime'])) {
            return;
        }

        $minimumAge = max($this->lifetimeSeconds, self::MINIMUM_TEMPORARY_FILE_AGE_SECONDS);

        if ($metadata['mtime'] + $minimumAge >= time()) {
            return;
        }

        $this->unlinkPath($file);
    }
}
