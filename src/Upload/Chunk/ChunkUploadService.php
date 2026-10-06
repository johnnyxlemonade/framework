<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Upload\ValueObject\UploadedFile as UploadResult;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\StreamInterface;

use function fclose;
use function fopen;
use function random_bytes;
use function strlen;

/**
 * Coordinates sequential chunk transport before delegating completed files to the existing upload pipeline.
 *
 * The service is request-scoped because it resolves the configured file or image uploader through UploadFactory.
 */
final readonly class ChunkUploadService
{
    private const int MAX_FILENAME_BYTES = 255;

    /**
     * Combines transport limits, temporary persistence, and the established profile-based upload boundary.
     */
    public function __construct(
        private ChunkUploadConfig $config,
        private FilesystemChunkUploadSessionStore $store,
        private UploadFactory $uploads,
    ) {
    }

    /**
     * Returns the server-authoritative maximum payload size for one append request.
     */
    public function chunkBytes(): int
    {
        return $this->config->chunkBytes();
    }

    /**
     * Creates a temporary session for an explicit file or image profile after resolving its maximum final size.
     *
     * The declared size is reserved as an upper bound before any chunk data is written.
     *
     * @param array<string,mixed> $context
     */
    public function start(
        string $kind,
        string $profile,
        string $originalFilename,
        int $declaredSize,
        array $context = [],
    ): ChunkUploadSession {
        $this->validateStart($kind, $profile, $originalFilename, $declaredSize);

        $maxBytes = $this->profileMaxBytes($kind, $profile);

        if ($declaredSize > $maxBytes) {
            throw new UploadValidationException('Declared upload size exceeds the configured maximum.');
        }

        try {
            $uploadId = bin2hex(random_bytes(32));
        } catch (\Throwable $exception) {
            throw new UploadStorageException('Chunk upload ID cannot be generated.', previous: $exception);
        }

        $createdAt = time();
        $session = new ChunkUploadSession(
            uploadId: $uploadId,
            kind: $kind,
            profile: $profile,
            originalFilename: $originalFilename,
            declaredSize: $declaredSize,
            maxBytes: $maxBytes,
            currentOffset: 0,
            createdAt: $createdAt,
            expiresAt: $createdAt + $this->config->ttlSeconds(),
            context: $context,
        );

        $this->store->create($session);

        return $session;
    }

    /**
     * Returns server-authoritative metadata for an active session without exposing its payload.
     */
    public function session(string $uploadId): ChunkUploadSessionDescriptor
    {
        $result = $this->store->withLock($uploadId, function () use ($uploadId): ChunkUploadSessionDescriptor {
            $session = $this->activeSession($uploadId);

            return new ChunkUploadSessionDescriptor(
                uploadId: $session->uploadId(),
                kind: $session->kind(),
                profile: $session->profile(),
                originalFilename: $session->originalFilename(),
                declaredSize: $session->declaredSize(),
                currentOffset: $session->currentOffset(),
                context: $session->context(),
            );
        });

        if (!$result instanceof ChunkUploadSessionDescriptor) {
            throw new UploadValidationException('Chunk upload session does not exist.');
        }

        return $result;
    }

    /**
     * Appends one non-empty permitted chunk at the exact next byte offset of an active session.
     *
     * The body is read under the session lock and cannot advance metadata unless the complete chunk is persisted.
     */
    public function append(
        string $uploadId,
        int $offset,
        StreamInterface $body,
    ): ChunkUploadSession {
        $result = $this->store->withLock($uploadId, function () use ($uploadId, $offset, $body): ChunkUploadSession {
            $session = $this->activeSession($uploadId);
            $payloadSize = $this->store->payloadSize($uploadId);

            if ($payloadSize !== $session->currentOffset()) {
                throw new UploadStorageException('Chunk upload payload does not match its metadata.');
            }

            if ($offset !== $session->currentOffset()) {
                throw new UploadValidationException('Chunk upload offset does not match the expected offset.');
            }

            $chunk = $this->readChunk($body);

            $nextOffset = $session->currentOffset() + strlen($chunk);

            if ($nextOffset > $session->declaredSize() || $nextOffset > $session->maxBytes()) {
                throw new UploadValidationException('Chunk upload exceeds its declared or configured size.');
            }

            return $this->store->append($session, $chunk);
        });

        if (!$result instanceof ChunkUploadSession) {
            throw new UploadValidationException('Chunk upload session does not exist.');
        }

        return $result;
    }

    /**
     * Verifies that the assembled payload is complete and submits it to the standard configured upload pipeline.
     *
     * Validation failures discard the temporary session, while unexpected storage or infrastructure failures leave it intact.
     */
    public function complete(string $uploadId): UploadResult|UploadedImage
    {
        $result = $this->store->withLock($uploadId, function () use ($uploadId): UploadResult|UploadedImage {
            $session = $this->activeSession($uploadId);
            $payloadSize = $this->store->payloadSize($uploadId);

            if (
                $session->currentOffset() !== $session->declaredSize()
                || $payloadSize !== $session->currentOffset()
                || $payloadSize !== $session->declaredSize()
                || $payloadSize > $session->maxBytes()
            ) {
                throw new UploadValidationException('Chunk upload is not complete.');
            }

            $resource = fopen($this->store->payloadPath($uploadId), 'rb');

            if ($resource === false) {
                throw new UploadStorageException('Chunk upload payload cannot be opened for completion.');
            }

            try {
                $file = new UploadedFile(
                    $resource,
                    $payloadSize,
                    UPLOAD_ERR_OK,
                    $session->originalFilename(),
                    'application/octet-stream',
                );

                $uploaded = $session->kind() === 'file'
                    ? $this->uploads->file($session->profile())->upload($file)
                    : $this->uploads->image($session->profile())->upload($file);
            } catch (UploadValidationException $exception) {
                $this->store->remove($uploadId);

                throw $exception;
            } finally {
                fclose($resource);
            }

            $this->store->remove($uploadId);

            return $uploaded;
        });

        if (!$result instanceof UploadResult) {
            throw new UploadValidationException('Chunk upload session does not exist.');
        }

        return $result;
    }

    /**
     * Idempotently removes a temporary session and all of its assembled data under its session lock.
     */
    public function abort(string $uploadId): void
    {
        $this->store->withLock($uploadId, function () use ($uploadId): void {
            $this->store->remove($uploadId);
        });
    }

    /**
     * Removes expired sessions and old unreadable metadata using the configured lifetime as a fallback.
     *
     * @return array{removed_sessions: int, released_bytes: int}
     */
    public function cleanupExpired(): array
    {
        $removedSessions = 0;
        $releasedBytes = 0;
        $now = time();

        foreach ($this->store->uploadIds() as $uploadId) {
            $result = $this->store->withLock($uploadId, function () use ($uploadId, $now): ?int {
                try {
                    $session = $this->store->load($uploadId);
                    $expired = $session === null || $session->isExpired($now);
                } catch (UploadStorageException) {
                    $expired = $this->store->sessionModifiedAt($uploadId) + $this->config->ttlSeconds() <= $now;
                }

                if (!$expired) {
                    return null;
                }

                $size = $this->store->payloadSize($uploadId);
                $this->store->remove($uploadId);

                return $size;
            });

            if (is_int($result)) {
                ++$removedSessions;
                $releasedBytes += $result;
            }
        }

        return [
            'removed_sessions' => $removedSessions,
            'released_bytes' => $releasedBytes,
        ];
    }

    private function profileMaxBytes(string $kind, string $profile): int
    {
        return $kind === 'file'
            ? $this->uploads->fileOptions($profile)->maxBytes()
            : $this->uploads->imageOptions($profile)->maxBytes();
    }

    private function validateStart(string $kind, string $profile, string $originalFilename, int $declaredSize): void
    {
        if (!in_array($kind, ['file', 'image'], true)) {
            throw new UploadValidationException('Chunk upload kind must be file or image.');
        }

        if ($profile === '') {
            throw new UploadValidationException('Chunk upload profile is required.');
        }

        if (
            $originalFilename === ''
            || str_contains($originalFilename, "\0")
            || strlen($originalFilename) > self::MAX_FILENAME_BYTES
        ) {
            throw new UploadValidationException('Chunk upload filename is invalid.');
        }

        if ($declaredSize <= 0) {
            throw new UploadValidationException('Chunk upload declared size must be greater than zero.');
        }
    }

    private function readChunk(StreamInterface $body): string
    {
        $limit = $this->config->chunkBytes();
        $chunk = '';

        while (!$body->eof() && strlen($chunk) <= $limit) {
            $chunk .= $body->read(min(8192, $limit + 1 - strlen($chunk)));
        }

        if ($chunk === '') {
            throw new UploadValidationException('Chunk upload payload cannot be empty.');
        }

        if (strlen($chunk) > $limit) {
            throw new UploadValidationException('Chunk upload payload exceeds the maximum chunk size.');
        }

        return $chunk;
    }

    private function activeSession(string $uploadId): ChunkUploadSession
    {
        $session = $this->store->load($uploadId);

        if (!$session instanceof ChunkUploadSession) {
            throw new UploadValidationException('Chunk upload session does not exist.');
        }

        if ($session->isExpired(time())) {
            $this->store->remove($uploadId);

            throw new UploadValidationException('Chunk upload session has expired.');
        }

        return $session;
    }
}
