<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Contract;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;

interface LockManagerInterface
{
    /**
     * Executes the given callback while holding an exclusive file lock.
     *
     * The lock file directory is created automatically if it does not exist.
     * The lock is released after the callback finishes, including when the
     * callback throws an exception.
     *
     * @throws FilesystemException If the lock file cannot be opened, the lock
     *                             cannot be acquired or released, or the callback fails.
     */
    public function lock(string $file, callable $callback): mixed;
}
