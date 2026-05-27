<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Contract;

use Generator;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use SplFileInfo;

interface DirectoryManagerInterface
{
    /**
     * Creates a directory including any missing parent directories.
     *
     * The operation is idempotent: if the directory already exists,
     * the method does nothing.
     *
     * @throws FilesystemException If the directory cannot be created.
     */
    public function create(string $path, int $mode = 0775): void;

    /**
     * Deletes a file, symbolic link, or directory.
     *
     * If the path is a directory, its contents are removed recursively.
     * If the path does not exist, the method does nothing.
     *
     * @throws FilesystemException If the path cannot be removed.
     */
    public function delete(string $path): void;

    /**
     * Copies a file or directory to the target path.
     *
     * Directories are copied recursively. If overwrite is disabled
     * and the target path already exists, an exception is thrown.
     *
     * @throws FilesystemException If the source does not exist, the target conflicts,
     *                             or the copy operation fails.
     */
    public function copy(string $src, string $dst, bool $overwrite = false): void;

    /**
     * Writes data to a file and creates the target directory if needed.
     *
     * If the file already exists, its contents are replaced.
     * The mode parameter controls the resulting file permissions;
     * null skips the chmod operation.
     *
     * @throws FilesystemException If writing the file or changing permissions fails.
     */
    public function write(string $file, string $data, ?int $mode = 0666): void;

    /**
     * Returns a lazy list of directory entries.
     *
     * The generator key is the entry name and the value is its absolute path.
     * Depending on the recursive flag, only the first level or the full tree is scanned.
     *
     * @return Generator<string, string>
     * @throws FilesystemException If the path is not a directory or cannot be read.
     */
    public function stream(string $path, bool $recursive = true): Generator;

    /**
     * Walks a directory and returns entries as SplFileInfo instances.
     *
     * Depending on the recursive flag, only the first level or the full tree is scanned.
     *
     * @return Generator<int, SplFileInfo>
     * @throws FilesystemException If the path is not a directory or cannot be traversed.
     */
    public function tree(string $path, bool $recursive = true): Generator;

    /**
     * Finds entries in a directory using a glob pattern.
     *
     * The search is limited to the given directory and is not recursive.
     *
     * @return Generator<int, string>
     * @throws FilesystemException If the path is not a directory or the search cannot be initialized.
     */
    public function find(string $pattern, string $path): Generator;
}
