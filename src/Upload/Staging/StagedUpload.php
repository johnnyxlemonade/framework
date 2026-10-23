<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Staging;

/**
 * Identifies one sealed private snapshot whose size and digest were measured while its bytes were written.
 *
 * @internal UploadService owns the snapshot lifecycle and removes it after publication or failure.
 */
final readonly class StagedUpload
{
    /**
     * Retains the private path and measured identity used to detect unexpected snapshot mutation.
     */
    public function __construct(
        private string $path,
        private string $clientFilename,
        private int $sizeBytes,
        private string $sha256,
        private ?StagedUploadLease $lease = null,
    ) {
    }

    /**
     * Returns the private local path used by server-side validators and publication infrastructure.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Returns the untrusted client basename retained only for extension-policy evaluation.
     */
    public function clientFilename(): string
    {
        return $this->clientFilename;
    }

    /**
     * Returns the authoritative byte count observed while creating the snapshot.
     */
    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    /**
     * Returns the digest of the exact bytes accepted into private staging.
     */
    public function sha256(): string
    {
        return $this->sha256;
    }

    /**
     * Releases the internal lifecycle lock after publication or failure cleanup begins.
     */
    public function releaseLease(): void
    {
        $this->lease?->release();
    }
}
