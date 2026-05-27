<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Contract;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;

interface FileManagerInterface
{
    /**
     * Reads the full contents of a file.
     *
     * @throws FilesystemException If the file does not exist, is not readable,
     *                             or cannot be read.
     */
    public function read(string $file): string;

    /**
     * Returns the file size in bytes.
     *
     * @throws FilesystemException If the file does not exist, is not a regular file,
     *                             or its size cannot be determined.
     */
    public function size(string $file): int;

    /**
     * Returns the last modification time as a UNIX timestamp.
     *
     * @throws FilesystemException If the path does not exist or its modification
     *                             time cannot be determined.
     */
    public function modified(string $file): int;

    /**
     * Calculates a file hash using the given algorithm.
     *
     * @throws FilesystemException If the file does not exist, is not a regular file,
     *                             or the hash cannot be calculated.
     */
    public function hash(string $file, string $algo = 'sha256'): string;

    /**
     * Detects the MIME type of a file.
     *
     * @throws FilesystemException If the file does not exist, is not a regular file,
     *                             or the MIME type cannot be determined.
     */
    public function mime(string $file): string;

    /**
     * Returns the file permissions as an octal permission mask.
     *
     * @throws FilesystemException If the path does not exist or its permissions
     *                             cannot be determined.
     */
    public function permissions(string $file): int;
}
