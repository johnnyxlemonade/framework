<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Filesystem;

use Generator;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Contract\FileManagerInterface;
use Lemonade\Framework\Filesystem\Contract\LockManagerInterface;
use Lemonade\Framework\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class FilesystemTest extends TestCase
{
    public function testFacadeDelegatesDirectoryOperationsAndReturnsManagers(): void
    {
        $directory = new DirectoryManagerSpy();
        $file = new FileManagerSpy();
        $lock = new LockManagerSpy();
        $filesystem = new Filesystem($directory, $file, $lock);

        $filesystem->create('/tmp/a', 0770);
        $filesystem->delete('/tmp/a');
        $filesystem->copy('/tmp/src', '/tmp/dst', true);
        $filesystem->write('/tmp/f.txt', 'x', 0644);
        iterator_to_array($filesystem->stream('/tmp', true));
        iterator_to_array($filesystem->find('*.txt', '/tmp'));
        iterator_to_array($filesystem->tree('/tmp', false));

        self::assertSame($directory, $filesystem->getDirectoryManager());
        self::assertSame($file, $filesystem->getFileManager());
        self::assertSame($lock, $filesystem->getLockManager());
        self::assertSame('create:/tmp/a:504', $directory->calls[0]);
        self::assertSame('delete:/tmp/a', $directory->calls[1]);
        self::assertSame('copy:/tmp/src:/tmp/dst:1', $directory->calls[2]);
        self::assertSame('write:/tmp/f.txt:x:420', $directory->calls[3]);
        self::assertSame('stream:/tmp:1', $directory->calls[4]);
        self::assertSame('find:*.txt:/tmp', $directory->calls[5]);
        self::assertSame('tree:/tmp:0', $directory->calls[6]);
    }

    public function testFacadeDelegatesFileOperations(): void
    {
        $directory = new DirectoryManagerSpy();
        $file = new FileManagerSpy();
        $lock = new LockManagerSpy();
        $filesystem = new Filesystem($directory, $file, $lock);

        self::assertSame('content', $filesystem->read('/tmp/f.txt'));
        self::assertSame(100, $filesystem->size('/tmp/f.txt'));
        self::assertSame(1234567890, $filesystem->modified('/tmp/f.txt'));
        self::assertSame('hash-sha1', $filesystem->hash('/tmp/f.txt', 'sha1'));
        self::assertSame('text/plain', $filesystem->mime('/tmp/f.txt'));
        self::assertSame(0644, $filesystem->permissions('/tmp/f.txt'));

        self::assertSame('read:/tmp/f.txt', $file->calls[0]);
        self::assertSame('size:/tmp/f.txt', $file->calls[1]);
        self::assertSame('modified:/tmp/f.txt', $file->calls[2]);
        self::assertSame('hash:/tmp/f.txt:sha1', $file->calls[3]);
        self::assertSame('mime:/tmp/f.txt', $file->calls[4]);
        self::assertSame('permissions:/tmp/f.txt', $file->calls[5]);
    }

    public function testFacadeDelegatesLockOperation(): void
    {
        $directory = new DirectoryManagerSpy();
        $file = new FileManagerSpy();
        $lock = new LockManagerSpy();
        $filesystem = new Filesystem($directory, $file, $lock);

        $result = $filesystem->lock('/tmp/lock.file', static fn(): string => 'locked');

        self::assertSame('locked', $result);
        self::assertSame('lock:/tmp/lock.file', $lock->calls[0]);
    }
}

final class DirectoryManagerSpy implements DirectoryManagerInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function create(string $path, int $mode = 0775): void
    {
        $this->calls[] = sprintf('create:%s:%d', $path, $mode);
    }

    public function delete(string $path): void
    {
        $this->calls[] = sprintf('delete:%s', $path);
    }

    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        $this->calls[] = sprintf('copy:%s:%s:%d', $src, $dst, $overwrite ? 1 : 0);
    }

    public function write(string $file, string $data, ?int $mode = 0666): void
    {
        $this->calls[] = sprintf('write:%s:%s:%d', $file, $data, $mode ?? -1);
    }

    public function stream(string $path, bool $recursive = true): Generator
    {
        $this->calls[] = sprintf('stream:%s:%d', $path, $recursive ? 1 : 0);
        yield from [];
    }

    public function tree(string $path, bool $recursive = true): Generator
    {
        $this->calls[] = sprintf('tree:%s:%d', $path, $recursive ? 1 : 0);
        yield from [];
    }

    public function find(string $pattern, string $path): Generator
    {
        $this->calls[] = sprintf('find:%s:%s', $pattern, $path);
        yield from [];
    }
}

final class FileManagerSpy implements FileManagerInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function read(string $file): string
    {
        $this->calls[] = sprintf('read:%s', $file);
        return 'content';
    }

    public function size(string $file): int
    {
        $this->calls[] = sprintf('size:%s', $file);
        return 100;
    }

    public function modified(string $file): int
    {
        $this->calls[] = sprintf('modified:%s', $file);
        return 1234567890;
    }

    public function hash(string $file, string $algo = 'sha256'): string
    {
        $this->calls[] = sprintf('hash:%s:%s', $file, $algo);
        return 'hash-' . $algo;
    }

    public function mime(string $file): string
    {
        $this->calls[] = sprintf('mime:%s', $file);
        return 'text/plain';
    }

    public function permissions(string $file): int
    {
        $this->calls[] = sprintf('permissions:%s', $file);
        return 0644;
    }
}

final class LockManagerSpy implements LockManagerInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function lock(string $file, callable $callback): mixed
    {
        $this->calls[] = sprintf('lock:%s', $file);
        return $callback();
    }
}
