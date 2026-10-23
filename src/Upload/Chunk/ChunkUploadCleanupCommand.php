<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Chunk;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Cli\CommandOutput;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Staging\UploadStaging;

/**
 * Provides the operational CLI entry point that reclaims expired sessions and orphaned upload snapshots.
 *
 * It uses only filesystem session state, so cleanup remains available outside an HTTP request scope.
 */
final readonly class ChunkUploadCleanupCommand implements CommandInterface
{
    /**
     * Initializes cleanup with the same expiry policy and private session store used by chunk uploads.
     */
    public function __construct(
        private ChunkUploadConfig $config,
        private FilesystemChunkUploadSessionStore $store,
        private UploadStaging $staging,
        private CommandOutput $output,
    ) {
    }

    /**
     * Returns the stable command name registered with the framework CLI.
     */
    public function name(): string
    {
        return 'upload:chunks:cleanup';
    }

    /**
     * Describes the maintenance operation shown by the CLI command list.
     */
    public function description(): string
    {
        return 'Remove expired chunk sessions and orphaned upload staging files.';
    }

    /**
     * Deletes expired or sufficiently old corrupt sessions and reports the reclaimed session and byte counts.
     *
     * @param list<string> $args Ignored command arguments; this command has no options
     */
    public function run(array $args): int
    {
        unset($args);

        try {
            $result = $this->cleanupExpired();
            $this->output->writeln(sprintf(
                'Removed sessions: %d; released bytes: %d; retired/initializing sessions: %d; staging orphans: %d',
                $result['removed_sessions'],
                $result['released_bytes'],
                $result['removed_retired'],
                $result['removed_staging'],
            ));

            return 0;
        } catch (\Throwable $exception) {
            $this->output->errorln('Chunk upload cleanup failed: ' . $exception->getMessage());

            return 1;
        }
    }

    /**
     * Performs locked expiration checks and returns the resources reclaimed during this invocation.
     *
     * @return array{
     *     removed_sessions: int,
     *     released_bytes: int,
     *     removed_retired: int,
     *     removed_staging: int
     * }
     */
    private function cleanupExpired(): array
    {
        $removedSessions = 0;
        $releasedBytes = 0;
        $now = time();
        $examined = 0;

        foreach ($this->store->uploadIds() as $uploadId) {
            if (++$examined > $this->config->cleanupBatchSize()) {
                break;
            }

            $result = $this->store->tryWithLock($uploadId, function () use ($uploadId, $now): ?int {
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

        $removedRetired = $this->store->cleanupRetired();
        $removedStaging = $this->staging->cleanupOrphans($now);

        return [
            'removed_sessions' => $removedSessions,
            'released_bytes' => $releasedBytes,
            'removed_retired' => $removedRetired,
            'removed_staging' => $removedStaging,
        ];
    }
}
