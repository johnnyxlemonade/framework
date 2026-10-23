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
 * Persists sliding sessions as owner-only files below one private root outside the public webroot.
 *
 * Reads and path mutations coordinate around atomic same-directory replacement. Concurrent payload
 * mutations remain last-writer-wins, while unsafe paths, ownership, and permissions fail closed.
 *
 * @internal Construct through SessionFactory so the active application public-root boundary is supplied.
 */
final class FileSessionStorage implements SessionStorageInterface
{
    private const string COOKIE_NAME = 'LEMONADE_SESSION';
    private const string GC_CURSOR_FILE = '.session-gc.cursor';
    private const string MUTATION_LOCK_FILE = '.session-files.lock';
    private const string TEMPORARY_FILE_PREFIX = '.session-write-';
    private const int CREATE_ATTEMPTS = 10;
    private const int DEFAULT_GC_BATCH_SIZE = 100;
    private const int MINIMUM_TEMPORARY_FILE_AGE_SECONDS = 300;

    private bool $started = false;
    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @var null|array{dev: int, ino: int}
     */
    private ?array $directoryIdentity = null;

    private readonly string $directory;
    private readonly string $publicRoot;
    private readonly int $ownerId;
    private readonly SessionCookie $cookie;
    private readonly SessionGarbageCollectionPolicy $garbageCollectionPolicy;
    private readonly SessionIdGenerator $sessionIdGenerator;

    /**
     * Defines a private absolute storage root, public-webroot boundary, retention, cookie, and bounded GC policy.
     *
     * @throws SessionStorageException When the configured filesystem boundary cannot be enforced safely
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
        $this->assertNonRootDirectory($this->directory);
        $this->assertSeparatedFromPublicRoot();
        $this->assertNoSymbolicLinkComponents($this->directory);

        $this->ownerId = $this->resolveProcessOwnerId();
        $this->cookie = $cookie ?? new SessionCookie($cookieName, $lifetimeSeconds);
        $this->garbageCollectionPolicy = $garbageCollectionPolicy ?? new SessionGarbageCollectionPolicy();
        $this->sessionIdGenerator = new SessionIdGenerator();
    }

    /**
     * Establishes a safe file identity, refreshes its sliding retention, and sends its cookie.
     *
     * @throws SessionStorageException When storage safety or an atomic filesystem operation cannot be verified
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
     * Stores a value through an owner-only atomic file replacement.
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
     * Removes the active owned file and expires its cookie so later access starts a new identity.
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
     * Copies the payload to a fresh identity and optionally invalidates the old owned file.
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
        $this->assertSeparatedFromPublicRoot();
        $this->assertNoSymbolicLinkComponents($this->directory);

        $missing = [];
        $cursor = $this->directory;

        while (!file_exists($cursor) && !is_link($cursor)) {
            $missing[] = $cursor;
            $parent = dirname($cursor);

            if ($parent === $cursor) {
                throw new SessionStorageException('File session root has no safe existing parent directory.');
            }

            $cursor = $parent;
        }

        if (is_link($cursor) || !is_dir($cursor)) {
            throw new SessionStorageException('File session root parent is not a safe directory.');
        }

        foreach (array_reverse($missing) as $directory) {
            try {
                $created = @mkdir($directory, 0700);
            } catch (Throwable $exception) {
                throw new SessionStorageException(
                    sprintf('File session directory "%s" cannot be created securely.', $directory),
                    previous: $exception,
                );
            }

            if (!$created && !is_dir($directory)) {
                throw new SessionStorageException(sprintf(
                    'File session directory "%s" cannot be created securely.',
                    $directory,
                ));
            }

            $this->secureDirectory($directory);
        }

        $stat = $this->secureDirectory($this->directory);
        $identity = $this->identityFromStat($stat);

        if ($this->directoryIdentity !== null && $identity !== $this->directoryIdentity) {
            throw new SessionStorageException('File session root identity changed while storage was active.');
        }

        $this->directoryIdentity ??= $identity;
        $this->assertNoSymbolicLinkComponents($this->directory);
        $this->assertCanonicalSeparationFromPublicRoot();
    }

    /**
     * @return array<mixed>
     */
    private function secureDirectory(string $directory): array
    {
        clearstatcache(true, $directory);
        $before = @lstat($directory);

        if (
            !is_array($before)
            || is_link($directory)
            || ($before['mode'] & 0170000) !== 0040000
            || $before['uid'] !== $this->ownerId
        ) {
            throw new SessionStorageException(sprintf(
                'File session directory "%s" must be a non-symbolic directory owned by the current process user.',
                $directory,
            ));
        }

        if (!@chmod($directory, 0700)) {
            throw new SessionStorageException(sprintf(
                'File session directory "%s" permissions cannot be restricted to mode 0700.',
                $directory,
            ));
        }

        clearstatcache(true, $directory);
        $after = @lstat($directory);

        if (
            !is_array($after)
            || is_link($directory)
            || ($after['mode'] & 0170000) !== 0040000
            || ($after['mode'] & 0777) !== 0700
            || $after['uid'] !== $this->ownerId
            || $after['dev'] !== $before['dev']
            || $after['ino'] !== $before['ino']
        ) {
            throw new SessionStorageException(sprintf(
                'File session directory "%s" did not retain safe ownership and mode 0700.',
                $directory,
            ));
        }

        return $after;
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
        $this->ensureDirectoryExists();
        $boundaryLock = $this->acquireMutationLock(LOCK_SH);
        $handle = null;

        try {
            if (!$this->pathExists($file)) {
                return null;
            }

            $this->secureRegularPath($file);
            $handle = @fopen($file, 'rb');

            if ($handle === false) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" cannot be opened for reading.',
                    $file,
                ));
            }

            if (!@flock($handle, LOCK_SH)) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" cannot be locked for reading.',
                    $file,
                ));
            }

            $metadata = $this->secureOpenFile($handle, $file);
            $identity = $this->identityFromStat($metadata);
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }

            throw $exception;
        } finally {
            $this->releaseLock($boundaryLock);
        }

        try {
            if ($metadata['mtime'] + $this->lifetimeSeconds < time()) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
                $handle = null;
                $this->deleteSessionFile($sessionId, $identity);

                return null;
            }

            $contents = stream_get_contents($handle);
            @flock($handle, LOCK_UN);

            if ($contents === false) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" cannot be read completely.',
                    $file,
                ));
            }

            if ($contents === '') {
                return null;
            }
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }
        }

        $data = @unserialize($contents, ['allowed_classes' => false]);

        if (!is_array($data)) {
            $this->deleteSessionFile($sessionId, $identity);

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
        $this->ensureDirectoryExists();
        $boundaryLock = $this->acquireMutationLock(LOCK_EX);
        $handle = null;

        try {
            if (!$this->pathExists($file)) {
                return false;
            }

            $this->secureRegularPath($file);
            $handle = @fopen($file, 'r+b');

            if ($handle === false) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" cannot be opened for retention refresh.',
                    $file,
                ));
            }

            if (!@flock($handle, LOCK_EX)) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" cannot be locked for retention refresh.',
                    $file,
                ));
            }

            $before = $this->secureOpenFile($handle, $file);

            if (!@touch($file)) {
                throw new SessionStorageException(sprintf(
                    'Session file "%s" retention cannot be refreshed.',
                    $file,
                ));
            }

            $after = $this->secureOpenFile($handle, $file);

            if ($this->identityFromStat($before) !== $this->identityFromStat($after)) {
                throw new SessionStorageException('Session file identity changed during retention refresh.');
            }

            return true;
        } finally {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }

            $this->releaseLock($boundaryLock);
        }
    }

    private function writeSessionFile(): void
    {
        $this->ensureDirectoryExists();
        $file = $this->filePath($this->sessionId);
        [$temporaryFile, $temporaryHandle] = $this->createTemporaryFile();
        $temporaryIdentity = null;

        try {
            $payload = serialize($this->data);
            $this->writeAll($temporaryHandle, $payload, $temporaryFile);

            if (!@fflush($temporaryHandle)) {
                throw new SessionStorageException('Temporary session payload cannot be flushed.');
            }

            if (function_exists('fsync') && !@fsync($temporaryHandle)) {
                throw new SessionStorageException('Temporary session payload cannot be synchronized.');
            }

            $temporaryStat = $this->secureOpenFile($temporaryHandle, $temporaryFile);
            $temporaryIdentity = $this->identityFromStat($temporaryStat);
            $boundaryLock = $this->acquireMutationLock(LOCK_EX);

            try {
                if ($this->pathExists($file)) {
                    $this->secureRegularPath($file);
                }

                if (!@rename($temporaryFile, $file)) {
                    throw new SessionStorageException(sprintf(
                        'Session file "%s" cannot be replaced atomically.',
                        $file,
                    ));
                }

                $temporaryFile = '';
                $published = $this->secureOpenFile($temporaryHandle, $file);

                if ($this->identityFromStat($published) !== $temporaryIdentity) {
                    throw new SessionStorageException('Published session file identity cannot be verified.');
                }
            } finally {
                $this->releaseLock($boundaryLock);
            }
        } finally {
            @flock($temporaryHandle, LOCK_UN);
            @fclose($temporaryHandle);

            if ($temporaryFile !== '') {
                $this->deleteOwnedTemporaryPath($temporaryFile, $temporaryIdentity);
            }
        }
    }

    /**
     * @return array{0: string, 1: resource}
     */
    private function createTemporaryFile(): array
    {
        $previous = null;

        for ($attempt = 0; $attempt < self::CREATE_ATTEMPTS; ++$attempt) {
            try {
                $path = sprintf(
                    '%s%s%s%s.tmp',
                    $this->directory,
                    DIRECTORY_SEPARATOR,
                    self::TEMPORARY_FILE_PREFIX,
                    bin2hex(random_bytes(32)),
                );
                $handle = @fopen($path, 'x+b');
            } catch (Throwable $exception) {
                $previous = $exception;

                continue;
            }

            if ($handle === false) {
                continue;
            }

            try {
                if (!@flock($handle, LOCK_EX)) {
                    throw new SessionStorageException('Temporary session file cannot be locked.');
                }

                $this->secureOpenFile($handle, $path);

                return [$path, $handle];
            } catch (Throwable $exception) {
                $previous = $exception;
                @flock($handle, LOCK_UN);
                @fclose($handle);
                $this->deleteOwnedTemporaryPath($path);
            }
        }

        throw new SessionStorageException(
            sprintf('A temporary session file cannot be created in "%s".', $this->directory),
            previous: $previous,
        );
    }

    /**
     * @param resource $handle
     */
    private function writeAll($handle, string $contents, string $path): void
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = @fwrite($handle, substr($contents, $offset));

            if (!is_int($written) || $written <= 0) {
                throw new SessionStorageException(sprintf(
                    'Temporary session file "%s" cannot be written completely.',
                    $path,
                ));
            }

            $offset += $written;
        }
    }

    /**
     * @param null|array{dev: int, ino: int} $expectedIdentity
     */
    private function deleteSessionFile(string $sessionId, ?array $expectedIdentity = null): bool
    {
        $file = $this->filePath($sessionId);
        $this->ensureDirectoryExists();
        $boundaryLock = $this->acquireMutationLock(LOCK_EX);

        try {
            if (!$this->pathExists($file)) {
                return false;
            }

            $stat = $this->secureRegularPath($file);

            if ($expectedIdentity !== null && $this->identityFromStat($stat) !== $expectedIdentity) {
                return false;
            }

            $this->unlinkOwnedPath($file, $stat);

            return true;
        } finally {
            $this->releaseLock($boundaryLock);
        }
    }

    private function deleteExpiredSessionFile(string $file): bool
    {
        $this->assertManagedPath($file, sessionFile: true);
        $this->ensureDirectoryExists();
        $boundaryLock = $this->acquireMutationLock(LOCK_EX);

        try {
            if (!$this->pathExists($file)) {
                return false;
            }

            $stat = $this->secureRegularPath($file);

            if ($stat['mtime'] + $this->lifetimeSeconds >= time()) {
                return false;
            }

            $this->unlinkOwnedPath($file, $stat);

            return true;
        } finally {
            $this->releaseLock($boundaryLock);
        }
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
        $this->ensureDirectoryExists();
        $cursorFile = $this->directory . DIRECTORY_SEPARATOR . self::GC_CURSOR_FILE;
        $cursor = $this->openManagedFile($cursorFile);

        try {
            if (!@flock($cursor, LOCK_EX | LOCK_NB)) {
                return;
            }

            $this->secureOpenFile($cursor, $cursorFile);
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

                if (
                    !$iterator->isDot()
                    && $name !== self::GC_CURSOR_FILE
                    && $name !== self::MUTATION_LOCK_FILE
                ) {
                    ++$processed;
                }

                $removedEntry = false;

                if ($this->isSessionFileName($name)) {
                    $removedEntry = $this->deleteExpiredSessionFile($iterator->getPathname());
                } elseif ($this->isTemporaryFileName($name)) {
                    $removedEntry = $this->deleteAbandonedTemporaryFile($iterator->getPathname());
                }

                if ($removedEntry) {
                    ++$removed;
                }

                $iterator->next();
            }

            $nextPosition = $iterator->valid()
                ? max(0, (int) $iterator->key() - $removed)
                : 0;
            $this->writeGcCursor($cursor, $cursorFile, $nextPosition);
            @flock($cursor, LOCK_UN);
        } finally {
            @fclose($cursor);
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
    private function writeGcCursor($cursor, string $cursorFile, int $position): void
    {
        rewind($cursor);
        $serialized = (string) $position;

        if (!@ftruncate($cursor, 0)) {
            throw new SessionStorageException('File session GC cursor cannot be truncated.');
        }

        $this->writeAll($cursor, $serialized, $cursorFile);

        if (!@fflush($cursor)) {
            throw new SessionStorageException('File session GC cursor cannot be flushed.');
        }

        $this->secureOpenFile($cursor, $cursorFile);
    }

    private function filePath(string $sessionId): string
    {
        if (!$this->sessionIdGenerator->isValid($sessionId)) {
            throw new SessionStorageException('Session identifier cannot be mapped to a managed file.');
        }

        return $this->directory
            . DIRECTORY_SEPARATOR
            . 'sess_'
            . $sessionId;
    }

    private function isSessionFileName(string $name): bool
    {
        return str_starts_with($name, 'sess_')
            && $this->sessionIdGenerator->isValid(substr($name, strlen('sess_')));
    }

    private function isTemporaryFileName(string $name): bool
    {
        return preg_match('/^\.session-write-[a-f0-9]{64}\.tmp$/D', $name) === 1;
    }

    private function deleteAbandonedTemporaryFile(string $file): bool
    {
        $this->assertManagedPath($file, temporaryFile: true);
        $this->ensureDirectoryExists();
        $boundaryLock = $this->acquireMutationLock(LOCK_EX);

        try {
            if (!$this->pathExists($file)) {
                return false;
            }

            $stat = $this->secureRegularPath($file);
            $minimumAge = max($this->lifetimeSeconds, self::MINIMUM_TEMPORARY_FILE_AGE_SECONDS);

            if ($stat['mtime'] + $minimumAge >= time()) {
                return false;
            }

            $this->unlinkOwnedPath($file, $stat);

            return true;
        } finally {
            $this->releaseLock($boundaryLock);
        }
    }

    /**
     * @param null|array{dev: int, ino: int} $expectedIdentity
     */
    private function deleteOwnedTemporaryPath(string $path, ?array $expectedIdentity = null): void
    {
        if (!$this->isTemporaryFileName(basename($path))) {
            throw new SessionStorageException('Refusing to remove a path outside the managed session temporary namespace.');
        }

        $this->assertManagedPath($path, temporaryFile: true);

        if (!$this->pathExists($path)) {
            return;
        }

        $stat = $this->secureRegularPath($path);

        if ($expectedIdentity !== null && $this->identityFromStat($stat) !== $expectedIdentity) {
            throw new SessionStorageException('Temporary session file identity changed before cleanup.');
        }

        $this->unlinkOwnedPath($path, $stat);
    }

    /**
     * @param int<0, 7> $operation
     * @return resource
     */
    private function acquireMutationLock(int $operation)
    {
        $lockFile = $this->directory . DIRECTORY_SEPARATOR . self::MUTATION_LOCK_FILE;
        $handle = $this->openManagedFile($lockFile);

        if (!@flock($handle, $operation)) {
            @fclose($handle);

            throw new SessionStorageException('File session mutation boundary cannot be locked.');
        }

        try {
            $this->secureOpenFile($handle, $lockFile);
            $this->assertDirectoryIdentity();
        } catch (Throwable $exception) {
            @flock($handle, LOCK_UN);
            @fclose($handle);

            throw $exception;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function releaseLock($handle): void
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    /**
     * @return resource
     */
    private function openManagedFile(string $path)
    {
        $this->assertManagedPath($path, controlFile: true);

        for ($attempt = 0; $attempt < self::CREATE_ATTEMPTS; ++$attempt) {
            clearstatcache(true, $path);

            if ($this->pathExists($path)) {
                $this->secureRegularPath($path);
                $handle = @fopen($path, 'r+b');
            } else {
                $handle = @fopen($path, 'x+b');
            }

            if ($handle === false) {
                continue;
            }

            try {
                $this->secureOpenFile($handle, $path);

                return $handle;
            } catch (Throwable $exception) {
                @fclose($handle);

                if ($this->pathExists($path)) {
                    throw $exception;
                }
            }
        }

        throw new SessionStorageException(sprintf(
            'Managed session control file "%s" cannot be opened safely.',
            $path,
        ));
    }

    /**
     * @param resource $handle
     * @return array<mixed>
     */
    private function secureOpenFile($handle, string $path): array
    {
        clearstatcache(true, $path);
        $opened = @fstat($handle);
        $current = @lstat($path);

        if (!$this->matchingOwnedRegularFile($opened, $current, $path)) {
            throw new SessionStorageException(sprintf(
                'Session file "%s" identity or ownership cannot be verified.',
                $path,
            ));
        }

        if (!@chmod($path, 0600)) {
            throw new SessionStorageException(sprintf(
                'Session file "%s" permissions cannot be restricted to mode 0600.',
                $path,
            ));
        }

        clearstatcache(true, $path);
        $securedOpen = @fstat($handle);
        $securedPath = @lstat($path);

        if (
            !is_array($securedOpen)
            || !is_array($securedPath)
            || !$this->matchingOwnedRegularFile($securedOpen, $securedPath, $path)
            || ($securedOpen['mode'] & 0777) !== 0600
            || ($securedPath['mode'] & 0777) !== 0600
        ) {
            throw new SessionStorageException(sprintf(
                'Session file "%s" did not retain safe ownership and mode 0600.',
                $path,
            ));
        }

        return $securedOpen;
    }

    /**
     * @return array<mixed>
     */
    private function secureRegularPath(string $path): array
    {
        clearstatcache(true, $path);
        $before = @lstat($path);

        if (!is_array($before) || !$this->ownedRegularFile($before, $path)) {
            throw new SessionStorageException(sprintf(
                'Session path "%s" must be a regular owner-only file owned by the current process user.',
                $path,
            ));
        }

        if (!@chmod($path, 0600)) {
            throw new SessionStorageException(sprintf(
                'Session file "%s" permissions cannot be restricted to mode 0600.',
                $path,
            ));
        }

        clearstatcache(true, $path);
        $after = @lstat($path);

        if (
            !is_array($after)
            || !$this->ownedRegularFile($after, $path)
            || ($after['mode'] & 0777) !== 0600
            || $after['dev'] !== $before['dev']
            || $after['ino'] !== $before['ino']
        ) {
            throw new SessionStorageException(sprintf(
                'Session file "%s" did not retain safe ownership and mode 0600.',
                $path,
            ));
        }

        return $after;
    }

    private function matchingOwnedRegularFile(mixed $opened, mixed $current, string $path): bool
    {
        if (!is_array($opened) || !is_array($current)) {
            return false;
        }

        return $this->ownedRegularFile($opened)
            && $this->ownedRegularFile($current, $path)
            && $opened['dev'] === $current['dev']
            && $opened['ino'] === $current['ino'];
    }

    private function ownedRegularFile(mixed $stat, ?string $path = null): bool
    {
        return is_array($stat)
            && ($path === null || !is_link($path))
            && ($stat['mode'] & 0170000) === 0100000
            && $stat['uid'] === $this->ownerId
            && $stat['nlink'] === 1;
    }

    /**
     * @param array<mixed> $stat
     */
    private function unlinkOwnedPath(string $path, array $stat): void
    {
        clearstatcache(true, $path);
        $current = @lstat($path);

        if (
            !is_array($current)
            || !$this->ownedRegularFile($current, $path)
            || $this->identityFromStat($current) !== $this->identityFromStat($stat)
        ) {
            throw new SessionStorageException(sprintf(
                'Session file "%s" identity changed before deletion.',
                $path,
            ));
        }

        if (!@unlink($path)) {
            throw new SessionStorageException(sprintf('Session file "%s" cannot be deleted.', $path));
        }

        clearstatcache(true, $path);

        if ($this->pathExists($path)) {
            throw new SessionStorageException(sprintf('Session file "%s" remains after deletion.', $path));
        }
    }

    private function assertManagedPath(
        string $path,
        bool $sessionFile = false,
        bool $temporaryFile = false,
        bool $controlFile = false,
    ): void {
        $normalized = $this->normalizePath($path);

        if ($normalized !== $this->directory . DIRECTORY_SEPARATOR . basename($normalized)) {
            throw new SessionStorageException('Session operation escaped the managed storage root.');
        }

        $name = basename($normalized);
        $allowed = ($sessionFile && $this->isSessionFileName($name))
            || ($temporaryFile && $this->isTemporaryFileName($name))
            || ($controlFile && in_array($name, [self::GC_CURSOR_FILE, self::MUTATION_LOCK_FILE], true));

        if (!$allowed) {
            throw new SessionStorageException('Session operation targeted an unmanaged filename.');
        }
    }

    private function assertDirectoryIdentity(): void
    {
        if ($this->directoryIdentity === null) {
            throw new SessionStorageException('File session root identity has not been established.');
        }

        clearstatcache(true, $this->directory);
        $stat = @lstat($this->directory);

        if (
            !is_array($stat)
            || is_link($this->directory)
            || ($stat['mode'] & 0170000) !== 0040000
            || ($stat['mode'] & 0777) !== 0700
            || $stat['uid'] !== $this->ownerId
            || $this->identityFromStat($stat) !== $this->directoryIdentity
        ) {
            throw new SessionStorageException('File session root identity, ownership, or permissions changed.');
        }
    }

    private function pathExists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    /**
     * @param array<mixed> $stat
     * @return array{dev: int, ino: int}
     */
    private function identityFromStat(array $stat): array
    {
        return [
            'dev' => (int) $stat['dev'],
            'ino' => (int) $stat['ino'],
        ];
    }

    private function resolveProcessOwnerId(): int
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }

        $probe = @tmpfile();

        if ($probe === false) {
            throw new SessionStorageException('Current process file ownership cannot be determined safely.');
        }

        try {
            $stat = @fstat($probe);

            if (!is_array($stat) || !isset($stat['uid']) || !is_int($stat['uid'])) {
                throw new SessionStorageException('Current process file ownership cannot be determined safely.');
            }

            return $stat['uid'];
        } finally {
            @fclose($probe);
        }
    }

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

    private function assertNonRootDirectory(string $path): void
    {
        if (
            dirname($path) === $path
            || $path === DIRECTORY_SEPARATOR
            || preg_match('/^[A-Za-z]:[\\\\\/]$/', $path) === 1
        ) {
            throw new SessionStorageException('File session root cannot be a filesystem root.');
        }
    }

    private function assertSeparatedFromPublicRoot(): void
    {
        if ($this->pathsOverlap($this->directory, $this->publicRoot)) {
            throw new SessionStorageException('File session storage must be separated from the public web root.');
        }

        $this->assertCanonicalSeparationFromPublicRoot();
    }

    private function assertCanonicalSeparationFromPublicRoot(): void
    {
        $directory = $this->canonicalCandidate($this->directory);
        $publicRoot = $this->canonicalCandidate($this->publicRoot);

        if ($directory === null || $publicRoot === null) {
            return;
        }

        if ($this->pathsOverlap($directory, $publicRoot)) {
            throw new SessionStorageException('Canonical file session storage overlaps the public web root.');
        }
    }

    private function canonicalCandidate(string $path): ?string
    {
        $suffix = [];
        $cursor = $path;

        while (!file_exists($cursor) && !is_link($cursor)) {
            array_unshift($suffix, basename($cursor));
            $parent = dirname($cursor);

            if ($parent === $cursor) {
                return null;
            }

            $cursor = $parent;
        }

        $resolved = @realpath($cursor);

        if (!is_string($resolved)) {
            return null;
        }

        if ($suffix !== []) {
            $resolved .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $suffix);
        }

        return $this->normalizePath($resolved);
    }

    private function pathsOverlap(string $first, string $second): bool
    {
        $first = $this->comparablePath($first);
        $second = $this->comparablePath($second);
        $firstPrefix = rtrim($first, '/\\') . DIRECTORY_SEPARATOR;
        $secondPrefix = rtrim($second, '/\\') . DIRECTORY_SEPARATOR;

        return $first === $second
            || str_starts_with($first, $secondPrefix)
            || str_starts_with($second, $firstPrefix);
    }

    private function assertNoSymbolicLinkComponents(string $path): void
    {
        $cursor = $path;

        while (true) {
            if (is_link($cursor)) {
                throw new SessionStorageException('File session root path cannot contain symbolic links.');
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                return;
            }

            $cursor = $parent;
        }
    }

    private function comparablePath(string $path): string
    {
        $path = $this->normalizePath($path);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

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
