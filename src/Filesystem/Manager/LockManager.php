<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Manager;

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

final class LockManager implements LockManagerInterface
{
    public function __construct(
        private readonly DirectoryManagerInterface $directoryManager,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function lock(string $file, callable $callback): mixed
    {
        $directory = dirname($file);

        if (!is_dir($directory)) {
            $this->directoryManager->create($directory);
        }

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new FilesystemException(
                "Failed to open lock file '{$file}'.",
                FilesystemException::CODE_LOCK_FAILED,
            );
        }

        $locked = false;

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new FilesystemException(
                    "Failed to acquire exclusive lock on '{$file}'.",
                    FilesystemException::CODE_LOCK_FAILED,
                );
            }

            $locked = true;

            return $callback();
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FilesystemException(
                "Lock callback failed on file '{$file}': {$exception->getMessage()}",
                FilesystemException::CODE_LOCK_FAILED,
                $exception,
            );
        } finally {
            if ($locked) {
                @flock($handle, LOCK_UN);
            }

            fclose($handle);
        }
    }
}
