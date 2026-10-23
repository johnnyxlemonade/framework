<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

use Closure;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Upload\Exception\ChunkUploadQuotaExceededException;
use Lemonade\Framework\Upload\Exception\UploadException;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\UploadPathPolicy;
use RuntimeException;

use function clearstatcache;
use function fclose;
use function fflush;
use function file_exists;
use function filesize;
use function fopen;
use function ftruncate;
use function fwrite;
use function is_dir;
use function is_file;
use function is_link;
use function json_decode;
use function json_encode;
use function lstat;
use function random_bytes;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

/**
 * Persists and serializes temporary chunk sessions below the application's writable path.
 *
 * It owns session metadata, assembled payload files, and per-session locks without exposing
 * client filenames or profiles as filesystem path components.
 *
 * @internal ChunkUploadService owns this persistence boundary.
 */
final readonly class FilesystemChunkUploadSessionStore
{
    private const string QUOTA_LOCK = '.quota.lock';
    private const string RETIRED_DIRECTORY = '.retired';
    private const string INITIALIZING_DIRECTORY = '.initializing';

    private string $root;
    /**
     * @var Closure(string, string): void
     */
    private Closure $atomicMove;

    /**
     * @var Closure(ChunkUploadSession): void|null
     */
    private ?Closure $afterSessionPublished;

    /**
     * Resolves private storage and retains the quota, path, and atomic-move boundaries used by session mutations.
     */
    public function __construct(
        private Filesystem $filesystem,
        private UploadPathPolicy $pathPolicy,
        private ChunkUploadConfig $config,
        ?Closure $atomicMove = null,
        ?Closure $afterSessionPublished = null,
    ) {
        $this->root = $this->pathPolicy->chunkRoot();
        $this->atomicMove = $atomicMove ?? $this->filesystem->move(...);
        $this->afterSessionPublished = $afterSessionPublished;
    }

    /**
     * Returns the private root under which all chunk session directories are stored.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * Atomically reserves global capacity and creates metadata without creating a payload file.
     *
     * @throws ChunkUploadQuotaExceededException When the active-session or reserved-byte quota is exhausted
     */
    public function create(ChunkUploadSession $session): void
    {
        [$initializingDirectory, $sessionLock] = $this->prepareSession($session);

        try {
            $this->withQuotaLock(function () use ($session, $initializingDirectory): void {
                $usage = $this->quotaUsage();

                if ($usage['sessions'] >= $this->config->maxActiveSessions()) {
                    throw new ChunkUploadQuotaExceededException(
                        'Chunk upload active-session quota has been reached.',
                    );
                }

                if (
                    $session->declaredSize() > $this->config->maxReservedBytes()
                    || $usage['reserved_bytes'] > $this->config->maxReservedBytes() - $session->declaredSize()
                ) {
                    throw new ChunkUploadQuotaExceededException(
                        'Chunk upload reserved-byte quota has been reached.',
                    );
                }

                $this->publishPreparedSession($session, $initializingDirectory);
            });

            if ($this->afterSessionPublished instanceof Closure) {
                ($this->afterSessionPublished)($session);
            }
        } finally {
            @flock($sessionLock, LOCK_UN);
            @fclose($sessionLock);

            if (is_dir($initializingDirectory) && !is_link($initializingDirectory)) {
                try {
                    $this->filesystem->delete($initializingDirectory);
                } catch (\Throwable) {
                    // A bounded cleanup pass reclaims interrupted initialization directories.
                }
            }
        }
    }

    /**
     * Runs a session operation while holding that session's exclusive filesystem lock.
     *
     * Missing sessions do not create lock files and return null; upload exceptions raised by the callback
     * retain their original category after the filesystem lock boundary.
     *
     * @template T
     * @param callable(): T $callback
     * @return T|null
     */
    public function withLock(string $uploadId, callable $callback): mixed
    {
        return $this->lockSession($uploadId, $callback, true);
    }

    /**
     * Runs a cleanup operation only when the session lock is immediately available.
     *
     * A missing, initializing, or currently active session returns null without waiting.
     *
     * @template T
     * @param callable(): T $callback
     * @return T|null
     */
    public function tryWithLock(string $uploadId, callable $callback): mixed
    {
        return $this->lockSession($uploadId, $callback, false);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T|null
     */
    private function lockSession(string $uploadId, callable $callback, bool $blocking): mixed
    {
        $this->prepareRoot();
        $directory = $this->sessionDirectory($uploadId);

        if (!is_dir($directory) || is_link($directory)) {
            return null;
        }

        $this->pathPolicy->assertChunkDirectory($directory);
        $lockPath = $directory . DIRECTORY_SEPARATOR . 'session.lock';

        if (is_link($lockPath)) {
            throw new UploadStorageException('Chunk upload lock path is unsafe.');
        }

        if (!is_file($lockPath)) {
            if (!$this->initializeLegacySessionLock($uploadId)) {
                return null;
            }
        }

        $handle = @fopen($lockPath, 'r+b');

        if ($handle === false) {
            return null;
        }

        $operation = $blocking ? LOCK_EX : LOCK_EX | LOCK_NB;

        if (!@flock($handle, $operation)) {
            @fclose($handle);

            if (!$blocking) {
                return null;
            }

            throw new UploadStorageException('Chunk upload session lock cannot be acquired.');
        }

        try {
            return $callback();
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * Rehydrates persisted metadata, or returns null when the session directory has no metadata file.
     */
    public function load(string $uploadId): ?ChunkUploadSession
    {
        $path = $this->metadataPath($uploadId);

        if (!is_file($path)) {
            return null;
        }

        if (is_link($path)) {
            throw new UploadStorageException('Chunk upload metadata path is unsafe.');
        }

        $metadataBytes = $this->filesystem->size($path);

        if ($metadataBytes > $this->config->maxContextBytes() + 4096) {
            throw new UploadStorageException('Chunk upload metadata exceeds its safe size limit.');
        }

        try {
            $decoded = json_decode($this->filesystem->read($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new UploadStorageException('Chunk upload metadata cannot be read.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new UploadStorageException('Chunk upload metadata is invalid.');
        }

        try {
            return new ChunkUploadSession(
                uploadId: $this->stringValue($decoded, 'upload_id'),
                kind: $this->stringValue($decoded, 'kind'),
                profile: $this->stringValue($decoded, 'profile'),
                originalFilename: $this->stringValue($decoded, 'original_filename'),
                declaredSize: $this->intValue($decoded, 'declared_size'),
                maxBytes: $this->intValue($decoded, 'max_bytes'),
                currentOffset: $this->intValue($decoded, 'current_offset'),
                chunkCount: $this->intValue($decoded, 'chunk_count'),
                maxChunks: $this->intValue($decoded, 'max_chunks'),
                createdAt: $this->intValue($decoded, 'created_at'),
                expiresAt: $this->intValue($decoded, 'expires_at'),
                context: $this->contextValue($decoded),
                publicationToken: $this->stringValue($decoded, 'publication_token'),
            );
        } catch (\Throwable $exception) {
            throw new UploadStorageException('Chunk upload metadata is invalid.', previous: $exception);
        }
    }

    /**
     * Returns the actual assembled payload size, treating a not-yet-created payload as empty.
     */
    public function payloadSize(string $uploadId): int
    {
        $path = $this->payloadPath($uploadId);

        clearstatcache(true, $path);

        if (!is_file($path)) {
            return 0;
        }

        if (is_link($path)) {
            throw new UploadStorageException('Chunk upload payload path is unsafe.');
        }

        $size = filesize($path);

        if ($size === false) {
            throw new UploadStorageException('Chunk upload payload size cannot be determined.');
        }

        return $size;
    }

    /**
     * Returns the internal assembled-payload path for a validated session identifier.
     */
    public function payloadPath(string $uploadId): string
    {
        return $this->sessionDirectory($uploadId) . DIRECTORY_SEPARATOR . 'payload.part';
    }

    /**
     * Appends one already validated chunk and publishes the advanced offset only after its full payload size is verified.
     *
     * A write or metadata failure truncates the payload back to its original size before the error escapes.
     */
    public function append(ChunkUploadSession $session, string $chunk): ChunkUploadSession
    {
        $path = $this->payloadPath($session->uploadId());
        $originalSize = $this->payloadSize($session->uploadId());

        if (is_link($path)) {
            throw new UploadStorageException('Chunk upload payload path is unsafe.');
        }

        $handle = fopen($path, 'c+b');

        if ($handle === false) {
            throw new UploadStorageException('Chunk upload payload cannot be opened.');
        }

        try {
            if (!@chmod($path, 0600)) {
                throw new RuntimeException('Chunk upload payload permissions cannot be restricted.');
            }

            $this->assertOpenedRegularFile($handle, $path);

            if (fseek($handle, 0, SEEK_END) !== 0) {
                throw new RuntimeException('Chunk upload payload cannot be positioned.');
            }

            $written = 0;
            $length = strlen($chunk);

            while ($written < $length) {
                $result = fwrite($handle, substr($chunk, $written));

                if ($result === false || $result === 0) {
                    throw new RuntimeException('Chunk upload payload cannot be written.');
                }

                $written += $result;
            }

            if (!fflush($handle)) {
                throw new RuntimeException('Chunk upload payload cannot be flushed.');
            }

            if ($this->payloadSize($session->uploadId()) !== $originalSize + $length) {
                throw new RuntimeException('Chunk upload payload size is invalid after append.');
            }

            $updated = $session->withAppendedChunk($originalSize + $length);
            $this->writeSession($updated);

            return $updated;
        } catch (\Throwable $exception) {
            if (!ftruncate($handle, max(0, $originalSize))) {
                throw new UploadStorageException(
                    'Chunk upload payload cannot be restored after a failed append.',
                    previous: $exception,
                );
            }

            throw $exception instanceof UploadStorageException
                ? $exception
                : new UploadStorageException('Chunk upload payload cannot be appended.', previous: $exception);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Atomically removes a session from the active namespace and then best-effort deletes its retired data.
     *
     * Once the same-filesystem rename succeeds, retries cannot observe or complete the session again.
     */
    public function remove(string $uploadId): void
    {
        $retired = $this->withQuotaLock(function () use ($uploadId): ?string {
            $directory = $this->sessionDirectory($uploadId);

            if (!is_dir($directory) || is_link($directory)) {
                return null;
            }

            $retiredRoot = $this->retiredRoot();
            $this->ensurePrivateDirectory($retiredRoot);

            try {
                $suffix = bin2hex(random_bytes(16));
            } catch (\Throwable $exception) {
                throw new UploadStorageException(
                    'Chunk upload retirement name cannot be generated.',
                    previous: $exception,
                );
            }

            $target = $retiredRoot . DIRECTORY_SEPARATOR . $uploadId . '-' . $suffix;
            ($this->atomicMove)($directory, $target);

            return $target;
        });

        if (!is_string($retired)) {
            return;
        }

        try {
            $this->filesystem->delete($retired);
        } catch (\Throwable) {
            // The atomic retirement is authoritative; opportunistic cleanup reclaims remaining bytes later.
        }
    }

    /**
     * Lazily yields syntactically valid session identifiers currently present below the chunk root.
     *
     * @return iterable<string>
     */
    public function uploadIds(): iterable
    {
        $this->prepareRoot();

        if (!is_dir($this->root)) {
            return [];
        }

        foreach ($this->filesystem->tree($this->root, false) as $shard) {
            if (
                $shard->isDir() === false
                || $shard->isLink()
                || preg_match('/^[a-f0-9]{2}$/', $shard->getFilename()) !== 1
            ) {
                continue;
            }

            foreach ($this->filesystem->tree($shard->getPathname(), false) as $session) {
                if (
                    $session->isDir()
                    && !$session->isLink()
                    && preg_match('/^[a-f0-9]{64}$/', $session->getFilename()) === 1
                ) {
                    yield $session->getFilename();
                }
            }
        }
    }

    /**
     * Best-effort removes a bounded number of retired or interrupted-initialization directories.
     */
    public function cleanupRetired(): int
    {
        $this->prepareRoot();
        $budget = $this->config->cleanupBatchSize();
        $retired = $this->cleanupRetiredDirectories($budget);
        $remaining = max(0, $budget - $retired['examined']);

        if ($remaining === 0) {
            return $retired['removed'];
        }

        $initializing = $this->cleanupInitializingDirectories($remaining);

        return $retired['removed'] + $initializing['removed'];
    }

    /**
     * @return array{removed: int, examined: int}
     */
    private function cleanupRetiredDirectories(int $budget): array
    {
        $root = $this->retiredRoot();

        if (!is_dir($root) || is_link($root)) {
            return ['removed' => 0, 'examined' => 0];
        }

        $removed = 0;
        $examined = 0;

        foreach ($this->filesystem->tree($root, false) as $entry) {
            if (++$examined > $budget) {
                --$examined;
                break;
            }

            if (!$entry->isDir() || $entry->isLink()) {
                continue;
            }

            try {
                $this->filesystem->delete($entry->getPathname());
                ++$removed;
            } catch (\Throwable) {
                // A later automatic or explicit cleanup pass may succeed.
            }
        }

        return ['removed' => $removed, 'examined' => $examined];
    }

    /**
     * @return array{removed: int, examined: int}
     */
    private function cleanupInitializingDirectories(int $budget): array
    {
        $root = $this->initializingRoot();

        if (!is_dir($root) || is_link($root)) {
            return ['removed' => 0, 'examined' => 0];
        }

        $removed = 0;
        $examined = 0;
        $cutoff = time() - $this->config->ttlSeconds();

        foreach ($this->filesystem->tree($root, false) as $entry) {
            if (++$examined > $budget) {
                --$examined;
                break;
            }

            $directory = $entry->getPathname();

            if (
                !$entry->isDir()
                || $entry->isLink()
                || preg_match('/^[a-f0-9]{64}-[a-f0-9]{32}$/', $entry->getFilename()) !== 1
                || !$this->isOldDirectory($directory, $cutoff)
            ) {
                continue;
            }

            $lockPath = $directory . DIRECTORY_SEPARATOR . 'session.lock';

            if (!is_file($lockPath) || is_link($lockPath)) {
                continue;
            }

            $handle = @fopen($lockPath, 'r+b');

            if ($handle === false) {
                continue;
            }

            try {
                if (!@flock($handle, LOCK_EX | LOCK_NB)) {
                    continue;
                }

                if (!$this->isOldDirectory($directory, $cutoff)) {
                    continue;
                }

                try {
                    $this->filesystem->delete($directory);
                    ++$removed;
                } catch (\Throwable) {
                    // A later bounded cleanup pass may succeed.
                }
            } finally {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }
        }

        return ['removed' => $removed, 'examined' => $examined];
    }

    /**
     * Returns the directory modification timestamp used as a cleanup fallback for unreadable metadata.
     */
    public function sessionModifiedAt(string $uploadId): int
    {
        return $this->filesystem->modified($this->sessionDirectory($uploadId));
    }

    private function writeSession(ChunkUploadSession $session, ?string $directory = null): void
    {
        $path = ($directory ?? $this->sessionDirectory($session->uploadId()))
            . DIRECTORY_SEPARATOR
            . 'meta.json';
        $temporary = $path . '.new';

        try {
            $json = json_encode([
                'upload_id' => $session->uploadId(),
                'kind' => $session->kind(),
                'profile' => $session->profile(),
                'original_filename' => $session->originalFilename(),
                'declared_size' => $session->declaredSize(),
                'max_bytes' => $session->maxBytes(),
                'current_offset' => $session->currentOffset(),
                'chunk_count' => $session->chunkCount(),
                'max_chunks' => $session->maxChunks(),
                'created_at' => $session->createdAt(),
                'expires_at' => $session->expiresAt(),
                'context' => $session->context(),
                'publication_token' => $session->publicationToken(),
            ], JSON_THROW_ON_ERROR);

            if (strlen($json) > $this->config->maxContextBytes() + 4096) {
                throw new RuntimeException('Chunk upload metadata exceeds its safe size limit.');
            }

            $this->filesystem->write($temporary, $json, 0600);
            $this->filesystem->move($temporary, $path);
        } catch (\Throwable $exception) {
            $this->filesystem->delete($temporary);

            throw new UploadStorageException('Chunk upload metadata cannot be written.', previous: $exception);
        }
    }

    private function sessionDirectory(string $uploadId): string
    {
        $this->assertUploadId($uploadId);

        return $this->root
            . DIRECTORY_SEPARATOR
            . substr($uploadId, 0, 2)
            . DIRECTORY_SEPARATOR
            . $uploadId;
    }

    /**
     * Creates complete metadata below the hidden initialization namespace while holding its session lock.
     *
     * @return array{string, resource}
     */
    private function prepareSession(ChunkUploadSession $session): array
    {
        $initializingRoot = $this->initializingRoot();
        $this->ensurePrivateDirectory($initializingRoot);

        try {
            $suffix = bin2hex(random_bytes(16));
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                'Chunk upload initialization name cannot be generated.',
                previous: $exception,
            );
        }

        $directory = $initializingRoot
            . DIRECTORY_SEPARATOR
            . $session->uploadId()
            . '-'
            . $suffix;

        if (file_exists($directory) || is_link($directory)) {
            throw new UploadStorageException('Chunk upload session already exists.');
        }

        $handle = null;

        try {
            $this->ensurePrivateDirectory($directory);
            $lockPath = $directory . DIRECTORY_SEPARATOR . 'session.lock';
            $handle = @fopen($lockPath, 'x+b');

            if ($handle === false || !@chmod($lockPath, 0600)) {
                throw new UploadStorageException('Chunk upload session lock cannot be created.');
            }

            if (!@flock($handle, LOCK_EX)) {
                throw new UploadStorageException('Chunk upload session lock cannot be acquired.');
            }

            $this->writeSession($session, $directory);

            return [$directory, $handle];
        } catch (\Throwable $exception) {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }

            $this->filesystem->delete($directory);

            if ($exception instanceof UploadException) {
                throw $exception;
            }

            throw new UploadStorageException('Chunk upload session cannot be created.', previous: $exception);
        }
    }

    private function publishPreparedSession(ChunkUploadSession $session, string $initializingDirectory): void
    {
        $directory = $this->sessionDirectory($session->uploadId());

        if (file_exists($directory) || is_link($directory)) {
            throw new UploadStorageException('Chunk upload session already exists.');
        }

        $this->ensurePrivateDirectory(dirname($directory));
        $this->filesystem->move($initializingDirectory, $directory);
    }

    private function initializeLegacySessionLock(string $uploadId): bool
    {
        $directory = $this->sessionDirectory($uploadId);
        $lockPath = $directory . DIRECTORY_SEPARATOR . 'session.lock';

        if (!is_dir($directory) || is_link($directory)) {
            return false;
        }

        if (is_link($lockPath)) {
            throw new UploadStorageException('Chunk upload lock path is unsafe.');
        }

        if (is_file($lockPath)) {
            return true;
        }

        $directoryModifiedAt = @filemtime($directory);
        $handle = @fopen($lockPath, 'x+b');

        if ($handle === false) {
            return $this->isSafeLockFile($lockPath);
        }

        try {
            if (!@chmod($lockPath, 0600)) {
                throw new UploadStorageException('Chunk upload lock permissions cannot be restricted.');
            }
        } finally {
            @fclose($handle);

            if (is_int($directoryModifiedAt)) {
                @touch($directory, $directoryModifiedAt);
            }
        }

        return true;
    }

    /**
     * A competing request may create the lock between the exclusive open and this check.
     *
     * @phpstan-impure
     */
    private function isSafeLockFile(string $lockPath): bool
    {
        clearstatcache(true, $lockPath);

        return is_file($lockPath) && !is_link($lockPath);
    }

    /**
     * @return array{sessions: int, reserved_bytes: int}
     */
    private function quotaUsage(): array
    {
        $sessions = 0;
        $reservedBytes = 0;

        foreach ($this->uploadIds() as $uploadId) {
            $session = $this->load($uploadId);

            if (!$session instanceof ChunkUploadSession) {
                throw new UploadStorageException('Chunk upload quota cannot classify an active session.');
            }

            ++$sessions;
            $declaredSize = $session->declaredSize();

            if ($reservedBytes > PHP_INT_MAX - $declaredSize) {
                throw new UploadStorageException('Chunk upload reserved-byte accounting overflowed.');
            }

            $reservedBytes += $declaredSize;
        }

        return [
            'sessions' => $sessions,
            'reserved_bytes' => $reservedBytes,
        ];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withQuotaLock(callable $callback): mixed
    {
        $this->prepareRoot();
        $lockPath = $this->root . DIRECTORY_SEPARATOR . self::QUOTA_LOCK;

        if (is_link($lockPath)) {
            throw new UploadStorageException('Chunk upload quota lock path is unsafe.');
        }

        try {
            return $this->filesystem->lock($lockPath, function () use ($callback, $lockPath): mixed {
                if (!@chmod($lockPath, 0600)) {
                    throw new UploadStorageException('Chunk upload quota lock permissions cannot be restricted.');
                }

                return $callback();
            });
        } catch (FilesystemException $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof UploadException) {
                throw $previous;
            }

            throw new UploadStorageException('Chunk upload quota cannot be updated.', previous: $exception);
        }
    }

    private function prepareRoot(): void
    {
        try {
            $this->pathPolicy->assertChunkRootSeparated();
            $this->pathPolicy->assertChunkDirectory($this->root);
            $this->ensurePrivateDirectory($this->root);
            $this->pathPolicy->assertChunkDirectory($this->root);
        } catch (UploadStorageException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new UploadStorageException('Chunk upload root is unsafe.', previous: $exception);
        }
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory)) {
            throw new UploadStorageException('Chunk upload directory cannot be a symbolic link.');
        }

        $this->pathPolicy->assertChunkDirectory($directory);
        $this->filesystem->create($directory, 0700);

        if (!@chmod($directory, 0700)) {
            throw new UploadStorageException('Chunk upload directory permissions cannot be restricted.');
        }

        $this->pathPolicy->assertChunkDirectory($directory);
    }

    /**
     * @param resource $handle
     */
    private function assertOpenedRegularFile($handle, string $path): void
    {
        $opened = fstat($handle);
        $pathStat = lstat($path);

        if (
            $opened === false
            || $pathStat === false
            || is_link($path)
            || ($opened['mode'] & 0170000) !== 0100000
            || $opened['dev'] !== $pathStat['dev']
            || $opened['ino'] !== $pathStat['ino']
        ) {
            throw new UploadStorageException('Chunk upload payload identity cannot be verified.');
        }
    }

    private function retiredRoot(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . self::RETIRED_DIRECTORY;
    }

    private function initializingRoot(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . self::INITIALIZING_DIRECTORY;
    }

    /** @phpstan-impure */
    private function isOldDirectory(string $directory, int $cutoff): bool
    {
        clearstatcache(true, $directory);
        $modified = @filemtime($directory);

        return is_dir($directory)
            && !is_link($directory)
            && is_int($modified)
            && $modified <= $cutoff;
    }

    private function metadataPath(string $uploadId): string
    {
        return $this->sessionDirectory($uploadId) . DIRECTORY_SEPARATOR . 'meta.json';
    }

    /**
     * Reads a required textual metadata field without coercing persisted values.
     *
     * @param array<mixed, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            throw new RuntimeException('Metadata value is not a string.');
        }

        return $value;
    }

    /**
     * Reads a required integer metadata field without accepting numeric strings.
     *
     * @param array<mixed, mixed> $data
     */
    private function intValue(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value)) {
            throw new RuntimeException('Metadata value is not an integer.');
        }

        return $value;
    }

    /**
     * Reads optional opaque context without assigning application semantics to it.
     *
     * @param array<mixed,mixed> $data
     * @return array<string,mixed>
     */
    private function contextValue(array $data): array
    {
        $context = $data['context'] ?? [];

        if (!is_array($context)) {
            return [];
        }

        $resolved = [];
        foreach ($context as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    private function assertUploadId(string $uploadId): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $uploadId) !== 1) {
            throw new UploadStorageException('Chunk upload ID is invalid.');
        }
    }
}
