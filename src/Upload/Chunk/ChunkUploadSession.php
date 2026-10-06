<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

use InvalidArgumentException;

/**
 * Carries immutable metadata for one active sequential upload assembled in temporary storage.
 *
 * Its offsets and expiry describe the trusted server-side state rather than client-supplied progress.
 */
final readonly class ChunkUploadSession
{
    /**
     * Creates a validated snapshot whose declared size and current offset cannot exceed the profile limit.
     */
    public function __construct(
        private string $uploadId,
        private string $kind,
        private string $profile,
        private string $originalFilename,
        private int $declaredSize,
        private int $maxBytes,
        private int $currentOffset,
        private int $createdAt,
        private int $expiresAt,
        /** @var array<string,mixed> */
        private array $context = [],
    ) {
        if (!in_array($this->kind, ['file', 'image'], true)) {
            throw new InvalidArgumentException('Chunk upload kind must be file or image.');
        }

        if ($this->uploadId === '' || $this->profile === '' || $this->originalFilename === '') {
            throw new InvalidArgumentException('Chunk upload session requires ID, profile, and original filename.');
        }

        if ($this->declaredSize <= 0 || $this->maxBytes <= 0 || $this->currentOffset < 0) {
            throw new InvalidArgumentException('Chunk upload session sizes are invalid.');
        }

        if ($this->currentOffset > $this->declaredSize || $this->declaredSize > $this->maxBytes) {
            throw new InvalidArgumentException('Chunk upload session offsets are invalid.');
        }

        if ($this->expiresAt <= $this->createdAt) {
            throw new InvalidArgumentException('Chunk upload session expiry must be after creation.');
        }
    }

    /**
     * Returns the unguessable identifier used to locate and lock this temporary session.
     */
    public function uploadId(): string
    {
        return $this->uploadId;
    }
    /**
     * Returns whether the session will complete through the generic file or image upload pipeline.
     */
    public function kind(): string
    {
        return $this->kind;
    }
    /**
     * Returns the configured upload profile whose policy governs completion.
     */
    public function profile(): string
    {
        return $this->profile;
    }
    /**
     * Returns the client filename retained for the existing extension validation boundary.
     */
    public function originalFilename(): string
    {
        return $this->originalFilename;
    }
    /**
     * Returns the total byte count the client declared before any payload was accepted.
     */
    public function declaredSize(): int
    {
        return $this->declaredSize;
    }
    /**
     * Returns the profile maximum captured when this session was started.
     */
    public function maxBytes(): int
    {
        return $this->maxBytes;
    }
    /**
     * Returns the next byte offset required from a sequential append request.
     */
    public function currentOffset(): int
    {
        return $this->currentOffset;
    }
    /**
     * Returns the UNIX timestamp at which the session was created.
     */
    public function createdAt(): int
    {
        return $this->createdAt;
    }
    /**
     * Returns the fixed UNIX timestamp after which the session must not accept more data.
     */
    public function expiresAt(): int
    {
        return $this->expiresAt;
    }

    /**
     * Returns application-owned opaque metadata persisted with this session.
     *
     * @return array<string,mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Reports whether the supplied UNIX timestamp is at or beyond this session's fixed expiry.
     */
    public function isExpired(int $now): bool
    {
        return $this->expiresAt <= $now;
    }

    /**
     * Returns a new metadata snapshot after a fully persisted append advances the byte offset.
     */
    public function withCurrentOffset(int $currentOffset): self
    {
        return new self(
            uploadId: $this->uploadId,
            kind: $this->kind,
            profile: $this->profile,
            originalFilename: $this->originalFilename,
            declaredSize: $this->declaredSize,
            maxBytes: $this->maxBytes,
            currentOffset: $currentOffset,
            createdAt: $this->createdAt,
            expiresAt: $this->expiresAt,
            context: $this->context,
        );
    }
}
