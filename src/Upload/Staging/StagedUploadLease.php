<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Staging;

/**
 * Keeps a shared lock alive while validators and publication consume one staging snapshot.
 *
 * @internal UploadStaging owns lease creation and release.
 */
final class StagedUploadLease
{
    /**
     * @var resource|null
     */
    private $handle;

    /**
     * Retains the already locked staging handle for the snapshot lifecycle.
     *
     * @param resource $handle
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Releases the snapshot lock exactly once so its file can be removed or reclaimed.
     */
    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        @fclose($this->handle);
        $this->handle = null;
    }

    /**
     * Releases an abandoned in-process lease while leaving its snapshot for age-based orphan cleanup.
     */
    public function __destruct()
    {
        $this->release();
    }
}
