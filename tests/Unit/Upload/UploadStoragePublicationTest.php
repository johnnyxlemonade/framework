<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Closure;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\Contract\FileManagerInterface;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Upload\UploadVisibility;
use PHPUnit\Framework\TestCase;

final class UploadStoragePublicationTest extends TestCase
{
    private string $basePath = '';
    private string $targetDirectory = '';
    private UploadPathPolicy $pathPolicy; // @phpstan-ignore property.uninitialized (initialized by PHPUnit setUp)
    private UploadStaging $staging; // @phpstan-ignore property.uninitialized (initialized by PHPUnit setUp)

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-publication-' . bin2hex(random_bytes(8));
        $this->targetDirectory = $this->basePath . '/public/uploads/files';
        mkdir($this->basePath . '/public', 0700, true);

        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->basePath, $this->basePath . '/public'),
            DebugMode::disabled(),
        );
        $this->pathPolicy = new UploadPathPolicy($context);
        $this->staging = new UploadStaging(
            new UploadPublicationTranslatorStub(),
            $this->filesystem(new FileManager()),
            $this->pathPolicy,
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            (new DirectoryManager())->delete($this->basePath);
        }
    }

    public function testPublishesOnlyCompleteSnapshotBytesAndRemovesPublicationTemporaryFile(): void
    {
        $snapshot = $this->staging->stageBytes('complete bytes', 'note.txt', 1024);
        $storage = $this->storage(new FileManager());
        $storage->ensureTargetDirectory($this->targetDirectory);

        $size = $storage->publishSnapshot(
            $snapshot,
            $this->targetDirectory,
            'published.txt',
            UploadVisibility::Public,
        );

        self::assertSame(14, $size);
        self::assertSame('complete bytes', file_get_contents($this->targetDirectory . '/published.txt'));
        self::assertSame([], $this->publicationTemporaryFiles());

        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0755, fileperms($this->targetDirectory) & 0777);
            self::assertSame(
                0700,
                fileperms($this->targetDirectory . '/.lemonade-publication') & 0777,
            );
            self::assertSame(0644, fileperms($this->targetDirectory . '/published.txt') & 0777);
        }
    }

    public function testRestrictsExistingPublicUploadRootAndNestedDirectories(): void
    {
        $uploadRoot = $this->basePath . '/public/uploads';
        mkdir($this->targetDirectory, 0777, true);
        chmod($uploadRoot, 0777);
        chmod($this->targetDirectory, 0777);

        $this->storage(new FileManager())->ensureTargetDirectory($this->targetDirectory);

        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0755, fileperms($uploadRoot) & 0777);
            self::assertSame(0755, fileperms($this->targetDirectory) & 0777);
        }
    }

    public function testConcurrentNameCollisionNeverOverwritesTheFile(): void
    {
        $snapshot = $this->staging->stageBytes('new bytes', 'note.txt', 1024);
        $storage = $this->storage(new FileManager());
        $storage->ensureTargetDirectory($this->targetDirectory);
        $target = $this->targetDirectory . '/collision.txt';
        file_put_contents($target, 'existing bytes');

        $this->expectException(UploadStorageException::class);

        try {
            $storage->publishSnapshot(
                $snapshot,
                $this->targetDirectory,
                'collision.txt',
                UploadVisibility::Public,
            );
        } finally {
            self::assertSame('existing bytes', file_get_contents($target));
            self::assertSame([], $this->publicationTemporaryFiles());
        }
    }

    public function testFailureAfterAtomicPublicationRollsBackTheNewTarget(): void
    {
        $snapshot = $this->staging->stageBytes('rollback bytes', 'note.txt', 1024);
        $storage = $this->storage(new IncorrectPublishedHashFileManager(new FileManager()));
        $storage->ensureTargetDirectory($this->targetDirectory);
        $target = $this->targetDirectory . '/rollback.txt';

        $this->expectException(UploadStorageException::class);

        try {
            $storage->publishSnapshot(
                $snapshot,
                $this->targetDirectory,
                'rollback.txt',
                UploadVisibility::Public,
            );
        } finally {
            self::assertFileDoesNotExist($target);
            self::assertSame([], $this->publicationTemporaryFiles());
        }
    }

    public function testChangedSnapshotCannotBePublished(): void
    {
        $snapshot = $this->staging->stageBytes('validated bytes', 'note.txt', 1024);
        chmod($snapshot->path(), 0600);
        file_put_contents($snapshot->path(), 'replacement');
        $storage = $this->storage(new FileManager());
        $storage->ensureTargetDirectory($this->targetDirectory);
        $target = $this->targetDirectory . '/changed.txt';

        $this->expectException(UploadStorageException::class);

        try {
            $storage->publishSnapshot(
                $snapshot,
                $this->targetDirectory,
                'changed.txt',
                UploadVisibility::Public,
            );
        } finally {
            self::assertFileDoesNotExist($target);
            self::assertSame([], $this->publicationTemporaryFiles());
        }
    }

    public function testUnsupportedHardLinksFailClosedWithoutFallbackOrTarget(): void
    {
        $snapshot = $this->staging->stageBytes('safe bytes', 'note.txt', 1024);
        $storage = $this->storage(
            new FileManager(),
            static fn(string $source, string $target): bool => false,
        );
        $storage->ensureTargetDirectory($this->targetDirectory);
        $target = $this->targetDirectory . '/unsupported.txt';

        $this->expectException(UploadStorageException::class);

        try {
            $storage->publishSnapshot(
                $snapshot,
                $this->targetDirectory,
                'unsupported.txt',
                UploadVisibility::Public,
            );
        } finally {
            self::assertFileDoesNotExist($target);
            self::assertSame([], $this->publicationTemporaryFiles());
        }
    }

    public function testReclaimsPublicationTemporaryLeftByTerminatedProcess(): void
    {
        $this->requireProcessControl();
        $pathFile = $this->basePath . '/publication-orphan-path';
        $snapshot = $this->staging->stageBytes('crash bytes', 'note.txt', 1024);
        $storage = $this->storage(
            new FileManager(),
            static function (string $source, string $target) use ($pathFile): bool {
                unset($target);
                file_put_contents($pathFile, $source);
                exit(0);
            },
        );
        $storage->ensureTargetDirectory($this->targetDirectory);
        $processId = pcntl_fork();

        if ($processId === -1) {
            self::fail('Publication crash process cannot be created.');
        }

        if ($processId === 0) {
            $storage->publishSnapshot(
                $snapshot,
                $this->targetDirectory,
                'never-published.txt',
                UploadVisibility::Public,
            );
            exit(4);
        }

        $status = 0;
        pcntl_waitpid($processId, $status);
        self::assertSame(0, pcntl_wexitstatus($status));
        $orphan = file_get_contents($pathFile);

        if (!is_string($orphan) || $orphan === '') {
            self::fail('Terminated publisher did not expose its temporary path.');
        }

        $now = time();
        touch($orphan, $now - ChunkUploadConfig::DEFAULT_STAGING_ORPHAN_TTL_SECONDS - 1);

        self::assertSame(
            1,
            $this->storage(new FileManager())->cleanupPublicationOrphans(
                $this->targetDirectory,
                UploadVisibility::Public,
                $now,
            ),
        );
        self::assertFileDoesNotExist($orphan);
        $this->staging->discard($snapshot);
    }

    public function testCleanupSkipsLockedPublicationTemporaryUntilLeaseRelease(): void
    {
        $storage = $this->storage(new FileManager());
        $storage->ensureTargetDirectory($this->targetDirectory);
        $temporaryDirectory = $this->publicationTemporaryDirectory();
        $temporary = $temporaryDirectory
            . '/.lemonade-publish-'
            . str_repeat('a', 32)
            . '.tmp';
        $handle = fopen($temporary, 'x+b');

        if ($handle === false || !flock($handle, LOCK_SH)) {
            self::fail('Active publication fixture cannot acquire its lease.');
        }

        fwrite($handle, 'active');
        fflush($handle);
        $now = time();
        touch($temporary, $now - ChunkUploadConfig::DEFAULT_STAGING_ORPHAN_TTL_SECONDS - 1);

        self::assertSame(
            0,
            $storage->cleanupPublicationOrphans(
                $this->targetDirectory,
                UploadVisibility::Public,
                $now,
            ),
        );
        self::assertFileExists($temporary);

        flock($handle, LOCK_UN);
        fclose($handle);

        self::assertSame(
            1,
            $storage->cleanupPublicationOrphans(
                $this->targetDirectory,
                UploadVisibility::Public,
                $now,
            ),
        );
    }

    public function testPublicationCleanupNeverFollowsTemporarySymlink(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $storage = $this->storage(new FileManager());
        $storage->ensureTargetDirectory($this->targetDirectory);
        $outside = $this->basePath . '/outside-publication.txt';
        file_put_contents($outside, 'unchanged');
        $link = $this->publicationTemporaryDirectory()
            . '/.lemonade-publish-'
            . str_repeat('b', 32)
            . '.tmp';

        if (!@symlink($outside, $link)) {
            self::markTestSkipped('Symbolic links cannot be created in the test filesystem.');
        }

        touch($outside, 1);

        self::assertSame(
            0,
            $storage->cleanupPublicationOrphans(
                $this->targetDirectory,
                UploadVisibility::Public,
                time(),
            ),
        );
        self::assertSame('unchanged', file_get_contents($outside));
        self::assertTrue(is_link($link));
    }

    private function storage(FileManagerInterface $fileManager, ?Closure $hardLink = null): UploadStorage
    {
        return new UploadStorage(
            new UploadPublicationTranslatorStub(),
            $this->filesystem($fileManager),
            $this->pathPolicy,
            $hardLink,
        );
    }

    private function filesystem(FileManagerInterface $fileManager): Filesystem
    {
        $directoryManager = new DirectoryManager();

        return new Filesystem(
            $directoryManager,
            $fileManager,
            new LockManager($directoryManager),
        );
    }

    /**
     * @return list<string>
     */
    private function publicationTemporaryFiles(): array
    {
        $files = glob($this->targetDirectory . '/.lemonade-publication/.lemonade-publish-*.tmp');

        return $files === false ? [] : $files;
    }

    private function publicationTemporaryDirectory(): string
    {
        $directory = $this->targetDirectory . '/.lemonade-publication';

        if (!is_dir($directory)) {
            mkdir($directory, 0700);
        }

        chmod($directory, 0700);

        return $directory;
    }

    private function requireProcessControl(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('The pcntl extension is required to simulate process termination.');
        }
    }
}

final class UploadPublicationTranslatorStub implements TranslatorInterface
{
    public function setLocale(?string $locale): self
    {
        unset($locale);

        return $this;
    }

    public function locale(): ?string
    {
        return null;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        unset($replacements, $locale);

        return $key;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($locale);

        return [$group => $group];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return ['messages' => ['ok' => 'ok']];
    }
}

final readonly class IncorrectPublishedHashFileManager implements FileManagerInterface
{
    public function __construct(private FileManagerInterface $inner)
    {
    }

    public function read(string $file): string
    {
        return $this->inner->read($file);
    }

    public function size(string $file): int
    {
        return $this->inner->size($file);
    }

    public function modified(string $file): int
    {
        return $this->inner->modified($file);
    }

    public function hash(string $file, string $algo = 'sha256'): string
    {
        if (str_contains($file, '/public/uploads/')) {
            return str_repeat('0', 64);
        }

        return $this->inner->hash($file, $algo);
    }

    public function mime(string $file): string
    {
        return $this->inner->mime($file);
    }

    public function permissions(string $file): int
    {
        return $this->inner->permissions($file);
    }
}
