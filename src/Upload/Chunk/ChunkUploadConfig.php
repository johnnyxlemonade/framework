<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

use InvalidArgumentException;

/**
 * Holds the global transport limits shared by every sequential chunk upload.
 *
 * The values deliberately remain independent of individual file and image profiles,
 * whose maximum assembled size is resolved separately.
 */
final readonly class ChunkUploadConfig
{
    public const int DEFAULT_CHUNK_BYTES = 2 * 1024 * 1024;
    public const int DEFAULT_TTL_SECONDS = 3600;

    /**
     * Initializes positive limits that bound one request payload and one session lifetime.
     */
    public function __construct(
        private int $chunkBytes = self::DEFAULT_CHUNK_BYTES,
        private int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
        if ($this->chunkBytes <= 0) {
            throw new InvalidArgumentException('Chunk upload chunk bytes must be greater than zero.');
        }

        if ($this->ttlSeconds <= 0) {
            throw new InvalidArgumentException('Chunk upload TTL must be greater than zero.');
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
}
