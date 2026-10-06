<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

/**
 * Read-only public snapshot of one active chunk upload session.
 */
final readonly class ChunkUploadSessionDescriptor
{
    /**
     * @param array<string,mixed> $context Application-owned opaque metadata
     */
    public function __construct(
        public string $uploadId,
        public string $kind,
        public string $profile,
        public string $originalFilename,
        public int $declaredSize,
        public int $currentOffset,
        public array $context,
    ) {
    }
}
