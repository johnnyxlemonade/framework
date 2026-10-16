<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem;

use Generator;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Contract\FileManagerInterface;
use Lemonade\Framework\Filesystem\Contract\LockManagerInterface;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use SplFileInfo;

/**
 * Exposes the framework's filesystem collaborators through one facade while retaining their typed failure contracts.
 */
final readonly class Filesystem
{
    /**
     * Composes the filesystem contracts exposed through this immutable facade.
     */
    public function __construct(
        private DirectoryManagerInterface $directoryManager,
        private FileManagerInterface $fileManager,
        private LockManagerInterface $lockManager,
    ) {
    }

    /**
     * Returns the collaborator responsible for filesystem mutations and traversal.
     */
    public function getDirectoryManager(): DirectoryManagerInterface
    {
        return $this->directoryManager;
    }

    /**
     * Returns the collaborator responsible for file reads and metadata.
     */
    public function getFileManager(): FileManagerInterface
    {
        return $this->fileManager;
    }

    /**
     * Returns the collaborator responsible for callback lock lifecycles.
     */
    public function getLockManager(): LockManagerInterface
    {
        return $this->lockManager;
    }

    /**
     * Creates a directory and any missing parents.
     *
     * @throws FilesystemException
     */
    public function create(string $path, int $mode = 0775): void
    {
        $this->directoryManager->create($path, $mode);
    }

    /**
     * Deletes a file, link, or directory tree without following linked directories.
     *
     * @throws FilesystemException
     */
    public function delete(string $path): void
    {
        $this->directoryManager->delete($path);
    }

    /**
     * Copies a regular file or directory without following symbolic links.
     *
     * @throws FilesystemException
     */
    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        $this->directoryManager->copy($src, $dst, $overwrite);
    }

    /**
     * Atomically publishes file contents from a same-directory temporary file.
     *
     * @throws FilesystemException
     */
    public function write(string $file, string $data, ?int $mode = 0666): void
    {
        $this->directoryManager->write($file, $data, $mode);
    }

    /**
     * Atomically renames a file within one local filesystem.
     *
     * @throws FilesystemException
     */
    public function move(string $source, string $target): void
    {
        $this->directoryManager->move($source, $target);
    }

    /**
     * Lazily yields direct entries or a complete tree without following linked directories.
     *
     * @return Generator<string, string>
     * @throws FilesystemException
     */
    public function stream(string $path, bool $recursive = true): Generator
    {
        return $this->directoryManager->stream($path, $recursive);
    }

    /**
     * Lazily yields direct glob matches constrained to the selected directory.
     *
     * @return Generator<int, string>
     * @throws FilesystemException
     */
    public function find(string $pattern, string $path): Generator
    {
        return $this->directoryManager->find($pattern, $path);
    }

    /**
     * Lazily yields entry metadata without following linked directories.
     *
     * @return Generator<int, SplFileInfo>
     * @throws FilesystemException
     */
    public function tree(string $path, bool $recursive = true): Generator
    {
        return $this->directoryManager->tree($path, $recursive);
    }

    /**
     * Reads the complete contents of a regular file.
     *
     * @throws FilesystemException
     */
    public function read(string $file): string
    {
        return $this->fileManager->read($file);
    }

    /**
     * Returns the size of a regular file in bytes.
     *
     * @throws FilesystemException
     */
    public function size(string $file): int
    {
        return $this->fileManager->size($file);
    }

    /**
     * Returns the modification time of an existing filesystem path.
     *
     * @throws FilesystemException
     */
    public function modified(string $file): int
    {
        return $this->fileManager->modified($file);
    }

    /**
     * Hashes a regular file with an explicitly supported algorithm.
     *
     * @throws FilesystemException
     */
    public function hash(string $file, string $algo = 'sha256'): string
    {
        return $this->fileManager->hash($file, $algo);
    }

    /**
     * Detects the MIME type of a regular file.
     *
     * @throws FilesystemException
     */
    public function mime(string $file): string
    {
        return $this->fileManager->mime($file);
    }

    /**
     * Returns the permission bits of an existing filesystem path.
     *
     * @throws FilesystemException
     */
    public function permissions(string $file): int
    {
        return $this->fileManager->permissions($file);
    }

    /**
     * Executes a callback while holding an exclusive lock.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws FilesystemException
     */
    public function lock(string $file, callable $callback): mixed
    {
        return $this->lockManager->lock($file, $callback);
    }
}
