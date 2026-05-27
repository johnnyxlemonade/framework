<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem;

use Generator;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;

use Lemonade\Framework\Filesystem\Contract\FileManagerInterface;
use Lemonade\Framework\Filesystem\Contract\LockManagerInterface;
use SplFileInfo;

final class Filesystem
{
    public function __construct(
        private readonly DirectoryManagerInterface $directoryManager,
        private readonly FileManagerInterface $fileManager,
        private readonly LockManagerInterface $lockManager,
    ) {}

    public function getDirectoryManager(): DirectoryManagerInterface
    {
        return $this->directoryManager;
    }

    public function getFileManager(): FileManagerInterface
    {
        return $this->fileManager;
    }

    public function getLockManager(): LockManagerInterface
    {
        return $this->lockManager;
    }

    public function create(string $path, int $mode = 0775): void
    {
        $this->directoryManager->create($path, $mode);
    }

    public function delete(string $path): void
    {
        $this->directoryManager->delete($path);
    }

    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        $this->directoryManager->copy($src, $dst, $overwrite);
    }

    public function write(string $file, string $data, ?int $mode = 0666): void
    {
        $this->directoryManager->write($file, $data, $mode);
    }

    /**
     * @return Generator<string, string>
     */
    public function stream(string $path, bool $recursive = true): Generator
    {
        return $this->directoryManager->stream($path, $recursive);
    }

    /**
     * @return Generator<int, string>
     */
    public function find(string $pattern, string $path): Generator
    {
        return $this->directoryManager->find($pattern, $path);
    }

    /**
     * @return Generator<int, SplFileInfo>
     */
    public function tree(string $path, bool $recursive = true): Generator
    {
        return $this->directoryManager->tree($path, $recursive);
    }

    public function read(string $file): string
    {
        return $this->fileManager->read($file);
    }

    public function size(string $file): int
    {
        return $this->fileManager->size($file);
    }

    public function modified(string $file): int
    {
        return $this->fileManager->modified($file);
    }

    public function hash(string $file, string $algo = 'sha256'): string
    {
        return $this->fileManager->hash($file, $algo);
    }

    public function mime(string $file): string
    {
        return $this->fileManager->mime($file);
    }

    public function permissions(string $file): int
    {
        return $this->fileManager->permissions($file);
    }

    public function lock(string $file, callable $callback): mixed
    {
        return $this->lockManager->lock($file, $callback);
    }
}
