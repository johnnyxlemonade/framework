<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Manager;

use Exception;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;

use Lemonade\Framework\Filesystem\Contract\LockManagerInterface;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Throwable;

use function dirname;
use function fclose;
use function flock;
use function fopen;
use function is_dir;

use const LOCK_EX;
use const LOCK_UN;

/**
 * Executes callbacks under an exclusive local file lock and preserves callback failures as filesystem causes.
 */
final readonly class LockManager implements LockManagerInterface
{
    /**
     * Retains the directory collaborator used to prepare lock-file parents.
     */
    public function __construct(
        private DirectoryManagerInterface $directoryManager,
    ) {
    }

    /**
     * Executes the callback under an exclusive lock and keeps callback failures primary during cleanup.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws FilesystemException
     */
    public function lock(string $file, callable $callback): mixed
    {
        $directory = dirname($file);

        if (!is_dir($directory)) {
            $this->directoryManager->create($directory);
        }

        try {
            $handle = @fopen($file, 'c+');
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to open lock file '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
                $exception,
            );
        }

        if ($handle === false) {
            throw new FilesystemException(
                "Failed to open lock file '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
            );
        }

        try {
            $locked = @flock($handle, LOCK_EX);
        } catch (Exception $exception) {
            $this->closeAfterFailure($handle);

            throw new FilesystemException(
                "Failed to acquire exclusive lock on '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
                $exception,
            );
        }

        if (!$locked) {
            $this->closeAfterFailure($handle);

            throw new FilesystemException(
                "Failed to acquire exclusive lock on '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
            );
        }

        $result = null;
        $callbackFailure = null;

        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $callbackFailure = $exception;
        }

        $unlockFailure = $this->unlock($handle, $file);
        $closeFailure = $this->close($handle, $file);

        if ($callbackFailure !== null) {
            if ($callbackFailure instanceof FilesystemException) {
                throw $callbackFailure;
            }

            throw new FilesystemException(
                "Lock callback failed on file '{$file}': {$callbackFailure->getMessage()}",
                FilesystemException::CODE_LOCK_FAILED,
                $callbackFailure,
            );
        }

        if ($unlockFailure !== null) {
            throw $unlockFailure;
        }

        if ($closeFailure !== null) {
            throw $closeFailure;
        }

        return $result;
    }

    /**
     * @param resource $handle
     */
    private function unlock($handle, string $file): ?FilesystemException
    {
        try {
            $unlocked = @flock($handle, LOCK_UN);
        } catch (Exception $exception) {
            return new FilesystemException(
                "Failed to release exclusive lock on '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
                $exception,
            );
        }

        if (!$unlocked) {
            return new FilesystemException(
                "Failed to release exclusive lock on '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
            );
        }

        return null;
    }

    /**
     * @param resource $handle
     */
    private function close($handle, string $file): ?FilesystemException
    {
        try {
            $closed = @fclose($handle);
        } catch (Exception $exception) {
            return new FilesystemException(
                "Failed to close lock file '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
                $exception,
            );
        }

        if (!$closed) {
            return new FilesystemException(
                "Failed to close lock file '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
            );
        }

        return null;
    }

    /**
     * @param resource $handle
     */
    private function closeAfterFailure($handle): void
    {
        try {
            @fclose($handle);
        } catch (Throwable) {
            // The acquisition failure remains primary.
        }
    }
}
