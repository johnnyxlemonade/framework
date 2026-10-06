<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Upload\Exception\UploadException;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
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
use function json_decode;
use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Persists and serializes temporary chunk sessions below the application's writable path.
 *
 * It owns session metadata, assembled payload files, and per-session locks without exposing
 * client filenames or profiles as filesystem path components.
 */
final readonly class FilesystemChunkUploadSessionStore
{
    private string $root;

    /**
     * Resolves the private chunk-upload root and retains the framework filesystem boundary used for mutations.
     */
    public function __construct(
        ApplicationContext $context,
        private Filesystem $filesystem,
    ) {
        $this->root = $context->resolveWritablePath('uploads/chunks');
    }

    /**
     * Returns the private root under which all chunk session directories are stored.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * Creates the session directory and writes its initial metadata without creating a payload file.
     */
    public function create(ChunkUploadSession $session): void
    {
        $directory = $this->sessionDirectory($session->uploadId());

        if (file_exists($directory)) {
            throw new UploadStorageException('Chunk upload session already exists.');
        }

        try {
            $this->filesystem->create($directory, 0700);
            $this->writeSession($session);
        } catch (\Throwable $exception) {
            $this->filesystem->delete($directory);

            throw $exception instanceof UploadStorageException
                ? $exception
                : new UploadStorageException('Chunk upload session cannot be created.', previous: $exception);
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
        $directory = $this->sessionDirectory($uploadId);

        if (!is_dir($directory)) {
            return null;
        }

        try {
            return $this->filesystem->lock(
                $directory . DIRECTORY_SEPARATOR . 'session.lock',
                $callback,
            );
        } catch (FilesystemException $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof UploadException) {
                throw $previous;
            }

            throw $exception;
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
                createdAt: $this->intValue($decoded, 'created_at'),
                expiresAt: $this->intValue($decoded, 'expires_at'),
                context: $this->contextValue($decoded),
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
        $handle = fopen($path, 'c+b');

        if ($handle === false) {
            throw new UploadStorageException('Chunk upload payload cannot be opened.');
        }

        try {
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

            $updated = $session->withCurrentOffset($originalSize + $length);
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
     * Physically removes the complete temporary session directory, including metadata and payload bytes.
     */
    public function remove(string $uploadId): void
    {
        $this->filesystem->delete($this->sessionDirectory($uploadId));
    }

    /**
     * Lazily yields syntactically valid session identifiers currently present below the chunk root.
     *
     * @return iterable<string>
     */
    public function uploadIds(): iterable
    {
        if (!is_dir($this->root)) {
            return [];
        }

        foreach ($this->filesystem->tree($this->root, false) as $shard) {
            if ($shard->isDir() === false || preg_match('/^[a-f0-9]{2}$/', $shard->getFilename()) !== 1) {
                continue;
            }

            foreach ($this->filesystem->tree($shard->getPathname(), false) as $session) {
                if ($session->isDir() && preg_match('/^[a-f0-9]{64}$/', $session->getFilename()) === 1) {
                    yield $session->getFilename();
                }
            }
        }
    }

    /**
     * Returns the directory modification timestamp used as a cleanup fallback for unreadable metadata.
     */
    public function sessionModifiedAt(string $uploadId): int
    {
        return $this->filesystem->modified($this->sessionDirectory($uploadId));
    }

    private function writeSession(ChunkUploadSession $session): void
    {
        $path = $this->metadataPath($session->uploadId());
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
                'created_at' => $session->createdAt(),
                'expires_at' => $session->expiresAt(),
                'context' => $session->context(),
            ], JSON_THROW_ON_ERROR);

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
