<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

use InvalidArgumentException;

/**
 * Holds safe default limits for chunk reservations, transport, metadata, and temporary cleanup.
 *
 * Applications with exceptional workloads may replace the container singleton while profile-specific
 * maximum file sizes remain independently enforced.
 */
final readonly class ChunkUploadConfig
{
    public const int DEFAULT_CHUNK_BYTES = 2 * 1024 * 1024;
    public const int DEFAULT_TTL_SECONDS = 3600;
    public const int DEFAULT_MAX_ACTIVE_SESSIONS = 128;
    public const int DEFAULT_MAX_RESERVED_BYTES = 512 * 1024 * 1024;
    public const int DEFAULT_MAX_CHUNKS_PER_SESSION = 1024;
    public const int DEFAULT_MAX_CONTEXT_BYTES = 16 * 1024;
    public const int DEFAULT_STAGING_ORPHAN_TTL_SECONDS = 24 * 3600;
    public const int DEFAULT_CLEANUP_BATCH_SIZE = 128;

    /**
     * Initializes positive transport, reservation, metadata, and opportunistic-cleanup limits.
     */
    public function __construct(
        private int $chunkBytes = self::DEFAULT_CHUNK_BYTES,
        private int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        private int $maxActiveSessions = self::DEFAULT_MAX_ACTIVE_SESSIONS,
        private int $maxReservedBytes = self::DEFAULT_MAX_RESERVED_BYTES,
        private int $maxChunksPerSession = self::DEFAULT_MAX_CHUNKS_PER_SESSION,
        private int $maxContextBytes = self::DEFAULT_MAX_CONTEXT_BYTES,
        private int $stagingOrphanTtlSeconds = self::DEFAULT_STAGING_ORPHAN_TTL_SECONDS,
        private int $cleanupBatchSize = self::DEFAULT_CLEANUP_BATCH_SIZE,
    ) {
        if ($this->chunkBytes <= 0) {
            throw new InvalidArgumentException('Chunk upload chunk bytes must be greater than zero.');
        }

        if ($this->ttlSeconds <= 0) {
            throw new InvalidArgumentException('Chunk upload TTL must be greater than zero.');
        }

        if ($this->maxActiveSessions <= 0) {
            throw new InvalidArgumentException('Chunk upload active-session limit must be greater than zero.');
        }

        if ($this->maxReservedBytes <= 0) {
            throw new InvalidArgumentException('Chunk upload reserved-byte limit must be greater than zero.');
        }

        if ($this->maxChunksPerSession <= 0) {
            throw new InvalidArgumentException('Chunk upload chunk-count limit must be greater than zero.');
        }

        if ($this->maxContextBytes <= 0) {
            throw new InvalidArgumentException('Chunk upload context limit must be greater than zero.');
        }

        if ($this->stagingOrphanTtlSeconds <= 0) {
            throw new InvalidArgumentException('Upload staging orphan TTL must be greater than zero.');
        }

        if ($this->cleanupBatchSize <= 0) {
            throw new InvalidArgumentException('Upload cleanup batch size must be greater than zero.');
        }
    }

    /**
     * Returns the server-authoritative maximum number of bytes accepted in one append request.
     */
    public function chunkBytes(): int
    {
        return $this->chunkBytes;
    }

    /**
     * Returns the fixed lifetime, in seconds, assigned when a session is created.
     */
    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    /**
     * Returns the maximum number of incomplete chunk sessions reserved at one time.
     */
    public function maxActiveSessions(): int
    {
        return $this->maxActiveSessions;
    }

    /**
     * Returns the maximum sum of declared bytes reserved by incomplete sessions.
     */
    public function maxReservedBytes(): int
    {
        return $this->maxReservedBytes;
    }

    /**
     * Returns the maximum number of append operations accepted by one session.
     */
    public function maxChunksPerSession(): int
    {
        return $this->maxChunksPerSession;
    }

    /**
     * Returns the maximum encoded byte count accepted for application-owned session context.
     */
    public function maxContextBytes(): int
    {
        return $this->maxContextBytes;
    }

    /**
     * Returns how old an unlocked staging snapshot must be before automatic orphan cleanup.
     */
    public function stagingOrphanTtlSeconds(): int
    {
        return $this->stagingOrphanTtlSeconds;
    }

    /**
     * Returns the maximum number of temporary entries examined by one opportunistic cleanup pass.
     */
    public function cleanupBatchSize(): int
    {
        return $this->cleanupBatchSize;
    }
}
