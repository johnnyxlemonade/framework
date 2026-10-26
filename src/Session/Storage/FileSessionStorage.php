<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use DirectoryIterator;
use InvalidArgumentException;
use Lemonade\Framework\Session\Exception\SessionStorageException;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use Lemonade\Framework\Session\SessionIdGenerator;
use OutOfBoundsException;
use Throwable;
use UnexpectedValueException;

/**
 * Persists sliding sessions in private atomically replaced files
 *
 * A short global lock coordinates path mutations. Reads use atomic snapshots,
 * while concurrent payload changes remain last-writer-wins.
 *
 * @internal Construct through SessionFactory so the public filesystem root is supplied
 */
final class FileSessionStorage implements SessionStorageInterface
{
    private const string COOKIE_NAME = 'LEMONADE_SESSION';
    private const string GC_CURSOR_FILE = '.session-gc.cursor';
    private const string MUTATION_LOCK_FILE = '.session-files.lock';
    private const string TEMPORARY_FILE_PREFIX = '.session-write-';
    private const int DEFAULT_GC_BATCH_SIZE = 100;
    private const int MINIMUM_TEMPORARY_FILE_AGE_SECONDS = 300;

    private bool $started = false;
    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private readonly string $directory;
    private readonly string $publicRoot;
    private readonly SessionCookie $cookie;
    private readonly SessionGarbageCollectionPolicy $garbageCollectionPolicy;
    private readonly SessionIdGenerator $sessionIdGenerator;

    /**
     * Defines private storage, retention, cookie identity and bounded garbage collection
     */
    public function __construct(
        string $directory,
        string $publicRoot,
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = self::COOKIE_NAME,
        private readonly int $gcBatchSize = self::DEFAULT_GC_BATCH_SIZE,
        ?SessionCookie $cookie = null,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
    ) {
        if ($lifetimeSeconds < 1) {
            throw new InvalidArgumentException('File session lifetime must be positive.');
        }
        if ($gcBatchSize < 1) {
            throw new InvalidArgumentException('File session GC batch size must be positive.');
        }

        $this->directory = $this->assertAbsoluteLocalPath($directory, 'File session root');
        $this->publicRoot = $this->assertAbsoluteLocalPath($publicRoot, 'Public web root');

        if (dirname($this->directory) === $this->directory || $this->directory === DIRECTORY_SEPARATOR) {
            throw new SessionStorageException('File session root cannot be a filesystem root.');
        }
        if ($this->pathsOverlap($this->directory, $this->publicRoot)) {
            throw new SessionStorageException('File session storage must be separated from the public web root.');
        }

        $this->assertCanonicalSeparationFromPublicRoot();

        if (@is_link($this->directory)) {
            throw new SessionStorageException('File session root cannot be a symbolic link.');
        }

        $this->cookie = $cookie ?? new SessionCookie($cookieName, $lifetimeSeconds);
        $this->garbageCollectionPolicy = $garbageCollectionPolicy ?? new SessionGarbageCollectionPolicy();
        $this->sessionIdGenerator = new SessionIdGenerator();
    }

    /**
     * Loads an existing session or creates a new identity
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->cookie->assertCanChange();
        $this->ensureDirectoryExists();

        if ($this->garbageCollectionPolicy->shouldRun()) {
            $this->collectGarbageBatch();
        }

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
     * Reports whether the session has started during this request
     */
    public function started(): bool
    {
        return $this->started;
    }

    /**
     * Checks whether a value exists, including an explicit null
     */
    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->data);
    }

    /**
     * Returns a stored value or the supplied fallback
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Stores a value through atomic session-file replacement
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();
        $this->data[$key] = $value;
        $this->writeSessionFile();
    }

    /**
     * Removes a value and atomically persists the remaining payload
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($this->data[$key]);
        $this->writeSessionFile();
    }

    /**
     * Clears the payload while retaining the current identity
     */
    public function clear(): void
    {
        $this->start();
        $this->data = [];
        $this->writeSessionFile();
    }

    /**
     * Deletes the active record, expires its cookie and releases its identity
     */
    public function destroy(): void
    {
        $this->start();
        $this->cookie->assertCanChange();
        $this->deleteSessionFile($this->sessionId);
        $this->cookie->expire();
        $this->started = false;
        $this->sessionId = '';
        $this->data = [];
    }

    /**
     * Moves the payload to a new identity and optionally deletes the old record
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

    /**
     * Creates and verifies the private session directory without traversing its parents
     */
    private function ensureDirectoryExists(): void
    {
        if (@is_link($this->directory)) {
            throw new SessionStorageException('File session root cannot be a symbolic link.');
        }

        if (!@is_dir($this->directory)) {
            try {
                $created = @mkdir($this->directory, 0700, true);
            } catch (Throwable $exception) {
                throw new SessionStorageException(
                    sprintf('File session directory "%s" cannot be created.', $this->directory),
                    previous: $exception,
                );
            }

            if (!$created && !@is_dir($this->directory)) {
                throw new SessionStorageException(sprintf(
                    'File session directory "%s" cannot be created.',
                    $this->directory,
                ));
            }
        }

        if (!@is_dir($this->directory)) {
            throw new SessionStorageException('File session root must be a non-symbolic directory.');
        }

        $this->restrictPermissions($this->directory, 0700, 'File session directory');
        $this->assertCanonicalSeparationFromPublicRoot();
    }

    /**
     * Returns a valid session identifier from the cookie or null
     */
    private function resolveSessionId(): ?string
    {
        $sessionId = $_COOKIE[$this->cookieName] ?? null;

        return is_string($sessionId) && $this->sessionIdGenerator->isValid($sessionId)
            ? $sessionId
            : null;
    }

    /**
     * Reads one complete atomically published session snapshot
     *
     * @return null|array<string, mixed>
     */
    private function readSessionFile(string $sessionId): ?array
    {
        $file = $this->filePath($sessionId);

        if (!$this->assertPrivateFile($file)) {
            return null;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            if (!@file_exists($file) && !@is_link($file)) {
                return null;
            }

            throw new SessionStorageException(sprintf('Session file "%s" cannot be opened.', $file));
        }

        try {
            $metadata = @fstat($handle);
            $contents = @stream_get_contents($handle);
        } finally {
            @fclose($handle);
        }

        if (!is_array($metadata) || !is_int($metadata['mtime'] ?? null)) {
            throw new SessionStorageException(sprintf('Session file "%s" metadata cannot be read.', $file));
        }
        if ($contents === false) {
            throw new SessionStorageException(sprintf('Session file "%s" cannot be read.', $file));
        }
        if ($metadata['mtime'] + $this->lifetimeSeconds < time()) {
            $this->deleteExpiredSessionFile($file);

            return null;
        }

        $data = $contents === '' ? false : @unserialize($contents, ['allowed_classes' => false]);
        if (!is_array($data)) {
            return null;
        }

        return array_filter($data, static fn(mixed $value, mixed $key): bool => is_string($key), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Resumes an active session and refreshes its sliding retention
     *
     * @param array<string, mixed> $data
     */
    private function resumeSession(string $sessionId, array $data): void
    {
        $this->sessionId = $sessionId;
        $this->data = $data;

        if (!$this->touchSessionFile($sessionId)) {
            $this->startNewSession();
        }
    }

    /**
     * Creates a new empty session with a random identity
     */
    private function startNewSession(): void
    {
        $this->sessionId = $this->sessionIdGenerator->generate();
        $this->data = [];
        $this->writeSessionFile();
    }

    /**
     * Refreshes an existing session mtime under the mutation lock
     */
    private function touchSessionFile(string $sessionId): bool
    {
        $file = $this->filePath($sessionId);
        $lock = $this->acquireMutationLock();

        try {
            if (!$this->assertPrivateFile($file)) {
                return false;
            }
            if (!@touch($file)) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" retention cannot be refreshed.',
                    $file,
                ));
            }

            return true;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Writes the payload to a temporary file and publishes it atomically
     */
    private function writeSessionFile(): void
    {
        $file = $this->filePath($this->sessionId);
        $temporaryFile = @tempnam($this->directory, self::TEMPORARY_FILE_PREFIX);
        $temporaryDirectory = is_string($temporaryFile) ? @realpath(dirname($temporaryFile)) : false;
        $sessionDirectory = @realpath($this->directory);

        if (
            !is_string($temporaryFile)
            || !is_string($temporaryDirectory)
            || !is_string($sessionDirectory)
            || $this->normalizePath($temporaryDirectory) !== $this->normalizePath($sessionDirectory)
        ) {
            if (is_string($temporaryFile)) {
                @unlink($temporaryFile);
            }

            throw new SessionStorageException(sprintf(
                'A temporary session file cannot be created in "%s".',
                $this->directory,
            ));
        }

        try {
            $this->restrictPermissions($temporaryFile, 0600, 'Temporary session file');
            $payload = serialize($this->data);
            $written = @file_put_contents($temporaryFile, $payload, LOCK_EX);

            if ($written !== strlen($payload)) {
                throw new SessionStorageException(sprintf(
                    'Temporary session file "%s" cannot be written completely.',
                    $temporaryFile,
                ));
            }

            $lock = $this->acquireMutationLock();

            try {
                if (@is_link($file)) {
                    throw new SessionStorageException('Session file cannot be a symbolic link.');
                }
                if (!@rename($temporaryFile, $file)) {
                    throw new SessionStorageException(sprintf(
                        'Session file "%s" cannot be replaced atomically.',
                        $file,
                    ));
                }

                $temporaryFile = '';
            } finally {
                $this->releaseLock($lock);
            }
        } finally {
            if ($temporaryFile !== '') {
                @unlink($temporaryFile);
            }
        }
    }

    /**
     * Deletes a session file under the mutation lock
     */
    private function deleteSessionFile(string $sessionId): bool
    {
        $file = $this->filePath($sessionId);
        $lock = $this->acquireMutationLock();

        try {
            if (!$this->assertPrivateFile($file)) {
                return false;
            }

            return $this->unlinkFile($file);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Deletes a session file only while it remains expired
     */
    private function deleteExpiredSessionFile(string $file): bool
    {
        $lock = $this->acquireMutationLock();

        try {
            if (!$this->assertPrivateFile($file)) {
                return false;
            }

            $modifiedAt = @filemtime($file);
            if (!is_int($modifiedAt)) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" modification time cannot be read.',
                    $file,
                ));
            }
            if ($modifiedAt + $this->lifetimeSeconds >= time()) {
                return false;
            }

            return $this->unlinkFile($file);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Processes one bounded portion of the session directory
     */
    private function collectGarbageBatch(): void
    {
        $cursorFile = $this->directory . DIRECTORY_SEPARATOR . self::GC_CURSOR_FILE;
        $cursor = $this->openControlFile($cursorFile);

        try {
            if (!@flock($cursor, LOCK_EX | LOCK_NB)) {
                return;
            }

            try {
                $position = $this->readGcCursor($cursor);

                try {
                    $iterator = new DirectoryIterator($this->directory);
                } catch (UnexpectedValueException $exception) {
                    throw new SessionStorageException(
                        'File session directory cannot be scanned for garbage collection.',
                        previous: $exception,
                    );
                }

                try {
                    $iterator->seek($position);
                } catch (OutOfBoundsException) {
                    $iterator->rewind();
                }

                $processed = 0;
                $removed = 0;

                while ($iterator->valid() && $processed < $this->gcBatchSize) {
                    $name = $iterator->getFilename();

                    if (!$iterator->isDot() && !in_array($name, [self::GC_CURSOR_FILE, self::MUTATION_LOCK_FILE], true)) {
                        ++$processed;
                    }

                    $removedEntry = false;

                    if (
                        str_starts_with($name, 'sess_')
                        && $this->sessionIdGenerator->isValid(substr($name, strlen('sess_')))
                    ) {
                        $removedEntry = $this->deleteExpiredSessionFile($iterator->getPathname());
                    } elseif (str_starts_with($name, self::TEMPORARY_FILE_PREFIX)) {
                        $removedEntry = $this->deleteAbandonedTemporaryFile($iterator->getPathname());
                    }

                    if ($removedEntry) {
                        ++$removed;
                    }

                    $iterator->next();
                }

                $nextPosition = $iterator->valid() ? max(0, (int) $iterator->key() - $removed) : 0;
                $this->writeGcCursor($cursor, $nextPosition);
            } finally {
                @flock($cursor, LOCK_UN);
            }
        } finally {
            @fclose($cursor);
        }
    }

    /**
     * Reads the last garbage-collection cursor position or starts at zero
     *
     * @param resource $cursor
     */
    private function readGcCursor($cursor): int
    {
        rewind($cursor);
        $value = @stream_get_contents($cursor);

        return is_string($value) && preg_match('/^[0-9]+$/D', trim($value)) === 1
            ? (int) trim($value)
            : 0;
    }

    /**
     * Replaces and flushes the garbage-collection cursor position
     *
     * @param resource $cursor
     */
    private function writeGcCursor($cursor, int $position): void
    {
        rewind($cursor);
        $value = (string) $position;

        if (
            !@ftruncate($cursor, 0)
            || @fwrite($cursor, $value) !== strlen($value)
            || !@fflush($cursor)
        ) {
            throw new SessionStorageException('File session GC cursor cannot be persisted.');
        }
    }

    /**
     * Deletes an abandoned temporary file after its retention threshold
     */
    private function deleteAbandonedTemporaryFile(string $file): bool
    {
        $lock = $this->acquireMutationLock();

        try {
            if (!$this->assertPrivateFile($file)) {
                return false;
            }

            $modifiedAt = @filemtime($file);
            if (!is_int($modifiedAt)) {
                throw new SessionStorageException(sprintf(
                    'Temporary session file "%s" modification time cannot be read.',
                    $file,
                ));
            }

            $minimumAge = max($this->lifetimeSeconds, self::MINIMUM_TEMPORARY_FILE_AGE_SECONDS);
            if ($modifiedAt + $minimumAge >= time()) {
                return false;
            }

            return $this->unlinkFile($file);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Builds a path only for a canonical session identifier
     */
    private function filePath(string $sessionId): string
    {
        if (!$this->sessionIdGenerator->isValid($sessionId)) {
            throw new SessionStorageException('Session identifier cannot be mapped to a managed file.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
    }

    /**
     * Acquires the short global lock for a managed path mutation
     *
     * @return resource
     */
    private function acquireMutationLock()
    {
        $handle = $this->openControlFile($this->directory . DIRECTORY_SEPARATOR . self::MUTATION_LOCK_FILE);

        if (!@flock($handle, LOCK_EX)) {
            @fclose($handle);
            throw new SessionStorageException('File session mutation boundary cannot be locked.');
        }

        return $handle;
    }

    /**
     * Releases and closes a held filesystem lock
     *
     * @param resource $handle
     */
    private function releaseLock($handle): void
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    /**
     * Opens or creates a private regular control file
     *
     * @return resource
     */
    private function openControlFile(string $path)
    {
        if (@is_link($path)) {
            throw new SessionStorageException('Managed session control file cannot be a symbolic link.');
        }

        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new SessionStorageException(sprintf(
                'Managed session control file "%s" cannot be opened.',
                $path,
            ));
        }

        $metadata = @fstat($handle);
        if (!is_array($metadata) || ($metadata['mode'] & 0170000) !== 0100000) {
            @fclose($handle);
            throw new SessionStorageException(sprintf(
                'Managed session control file "%s" must be regular.',
                $path,
            ));
        }

        try {
            $this->restrictPermissions($path, 0600, 'Managed session control file');
        } catch (Throwable $exception) {
            @fclose($handle);
            throw $exception;
        }

        return $handle;
    }

    /**
     * Verifies a non-symbolic regular file and applies mode 0600
     */
    private function assertPrivateFile(string $path): bool
    {
        if (@is_link($path)) {
            throw new SessionStorageException(sprintf('Session path "%s" cannot be a symbolic link.', $path));
        }
        if (!@file_exists($path)) {
            return false;
        }
        if (!@is_file($path)) {
            throw new SessionStorageException(sprintf('Session path "%s" must be a regular file.', $path));
        }

        $this->restrictPermissions($path, 0600, 'Session file');

        return true;
    }

    /**
     * Unlinks a file and verifies the result
     */
    private function unlinkFile(string $path): bool
    {
        if (@unlink($path)) {
            return true;
        }
        if (!@file_exists($path) && !@is_link($path)) {
            return false;
        }

        throw new SessionStorageException(sprintf('Session file "%s" cannot be deleted.', $path));
    }

    /**
     * Applies and verifies the exact private mode of a path
     */
    private function restrictPermissions(string $path, int $mode, string $label): void
    {
        $permissions = @fileperms($path);
        if (!is_int($permissions)) {
            throw new SessionStorageException(sprintf('%s "%s" permissions cannot be read.', $label, $path));
        }
        if (($permissions & 0777) === $mode) {
            return;
        }
        if (!@chmod($path, $mode)) {
            throw new SessionStorageException(sprintf(
                '%s "%s" permissions cannot be restricted to mode %s.',
                $label,
                $path,
                decoct($mode),
            ));
        }

        $permissions = @fileperms($path);
        if (!is_int($permissions) || ($permissions & 0777) !== $mode) {
            throw new SessionStorageException(sprintf('%s "%s" did not retain mode %s.', $label, $path, decoct($mode)));
        }
    }

    /**
     * Verifies an absolute local path without traversal segments
     */
    private function assertAbsoluteLocalPath(string $path, string $label): string
    {
        $portable = str_replace('\\', '/', $path);
        $segments = explode('/', $portable);
        $absolute = DIRECTORY_SEPARATOR === '\\'
            ? preg_match('/^[A-Za-z]:\//', $portable) === 1
            : str_starts_with($portable, '/') && !str_starts_with($portable, '//');

        if (
            $path === ''
            || trim($path) !== $path
            || str_contains($path, "\0")
            || str_contains($path, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || !$absolute
        ) {
            throw new SessionStorageException($label . ' must be an absolute local path without traversal.');
        }

        return $this->normalizePath($path);
    }

    /**
     * Rejects canonical overlap between existing session and public directories
     */
    private function assertCanonicalSeparationFromPublicRoot(): void
    {
        $directory = $this->canonicalCandidate($this->directory);
        $publicRoot = $this->canonicalCandidate($this->publicRoot);

        if (is_string($directory) && is_string($publicRoot) && $this->pathsOverlap($directory, $publicRoot)) {
            throw new SessionStorageException('Canonical file session storage overlaps the public web root.');
        }
    }

    /**
     * Returns a real path or appends one missing final segment
     */
    private function canonicalCandidate(string $path): ?string
    {
        $resolved = @realpath($path);
        if (is_string($resolved)) {
            return $this->normalizePath($resolved);
        }

        $parent = @realpath(dirname($path));

        return is_string($parent)
            ? $this->normalizePath($parent . DIRECTORY_SEPARATOR . basename($path))
            : null;
    }

    /**
     * Reports whether two normalized paths overlap
     */
    private function pathsOverlap(string $first, string $second): bool
    {
        $first = $this->normalizePath($first);
        $second = $this->normalizePath($second);

        if (DIRECTORY_SEPARATOR === '\\') {
            $first = strtolower($first);
            $second = strtolower($second);
        }

        $firstPrefix = rtrim($first, '/\\') . DIRECTORY_SEPARATOR;
        $secondPrefix = rtrim($second, '/\\') . DIRECTORY_SEPARATOR;

        return $first === $second
            || str_starts_with($first, $secondPrefix)
            || str_starts_with($second, $firstPrefix);
    }

    /**
     * Normalizes separators and removes a trailing separator
     */
    private function normalizePath(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:\\\\?$/', $normalized) === 1) {
            return strtoupper($normalized[0]) . ':\\';
        }

        $trimmed = rtrim($normalized, '/\\');

        return $trimmed === '' ? DIRECTORY_SEPARATOR : $trimmed;
    }
}
