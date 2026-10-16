<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Contract;

use Generator;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use SplFileInfo;

/**
 * Defines local filesystem mutations and lazy directory traversal with typed failures instead of native warnings.
 */
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
     * If the path is a directory, its contents are removed recursively without following
     * symbolic links. Deleting a symbolic link removes only the link.
     * If the path does not exist, the method does nothing.
     *
     * @throws FilesystemException If the path cannot be removed.
     */
    public function delete(string $path): void;

    /**
     * Copies a regular file or directory to the target path.
     *
     * Directories are copied recursively without following symbolic links; a symbolic link
     * source or entry is rejected. Files are published from a same-directory temporary file,
     * so a failed file copy preserves an existing target. If overwrite is disabled, an
     * existing target is rejected, but this check is not a concurrent compare-and-swap.
     *
     * A newly created target directory is removed after a failed recursive copy when possible.
     * Merging into an existing directory may leave entries copied before the failure.
     *
     * @throws FilesystemException If the source does not exist, the target conflicts,
     *                             or the copy operation fails.
     */
    public function copy(string $src, string $dst, bool $overwrite = false): void;

    /**
     * Writes data to a file and creates the target directory if needed.
     *
     * Data is written to a same-directory temporary file and renamed into place, so failures
     * before publication preserve an existing file. This guarantees atomic visibility on the
     * local filesystem, not crash durability.
     * The mode parameter controls the resulting file permissions. A null mode preserves
     * an existing target's permission bits and otherwise keeps the creation mode.
     *
     * @throws FilesystemException If writing the file or changing permissions fails.
     */
    public function write(string $file, string $data, ?int $mode = 0666): void;

    /**
     * Atomically publishes a fully written file at its final path on the same local filesystem.
     *
     * @throws FilesystemException If the source cannot be renamed to the target.
     */
    public function move(string $source, string $target): void;

    /**
     * Returns a lazy list of directory entries.
     *
     * The generator key is the entry name and the value is its absolute path. Symbolic links
     * are yielded as entries inside the scanned tree and linked directories are not followed.
     * Depending on the recursive flag, only the first level or the full tree is scanned.
     * Linked directories are yielded but never traversed.
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
     * The search is limited to the given directory and is not recursive. Patterns containing
     * path separators are rejected. Matching symbolic links are returned without being followed.
     *
     * @return Generator<int, string>
     * @throws FilesystemException If the path is not a directory or the search cannot be initialized.
     */
    public function find(string $pattern, string $path): Generator;
}
