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
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadPathPolicy;
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
        $this->targetDirectory = $this->basePath . '/storage/uploads/files';
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
        $publicationDirectory = $this->targetDirectory . '/.lemonade-publication';
        mkdir($publicationDirectory, 0755);

        if (DIRECTORY_SEPARATOR !== '\\') {
            chmod($publicationDirectory, 0755);
        }

        $size = $storage->publishSnapshot(
            $snapshot,
            $this->targetDirectory,
            'published.txt',
        );

        self::assertSame(14, $size);
        self::assertSame('complete bytes', file_get_contents($this->targetDirectory . '/published.txt'));
        self::assertSame([], $this->publicationTemporaryFiles());

        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0700, fileperms($this->targetDirectory) & 0777);
            self::assertSame(
                0755,
                fileperms($publicationDirectory) & 0777,
            );
            self::assertSame(0600, fileperms($this->targetDirectory . '/published.txt') & 0777);
        }
    }

    public function testPreservesExistingUploadDirectoryPermissions(): void
    {
        $uploadRoot = $this->basePath . '/storage/uploads';
        mkdir($this->targetDirectory, 0777, true);
        chmod($uploadRoot, 0777);
        chmod($this->targetDirectory, 0777);

        $this->storage(new FileManager())->ensureTargetDirectory($this->targetDirectory);

        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0777, fileperms($uploadRoot) & 0777);
            self::assertSame(0777, fileperms($this->targetDirectory) & 0777);
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
            );
        } finally {
            self::assertSame('existing bytes', file_get_contents($target));
            self::assertSame([], $this->publicationTemporaryFiles());
        }
    }

    public function testFailureAfterAtomicPublicationRollsBackTheNewTarget(): void
    {
        $snapshot = $this->staging->stageBytes('rollback bytes', 'note.txt', 1024);
        $storage = $this->storage(
            new FileManager(),
            static function (string $source, string $target): bool {
                if (!link($source, $target)) {
                    return false;
                }

                unlink($source);

                return true;
            },
        );
        $storage->ensureTargetDirectory($this->targetDirectory);
        $target = $this->targetDirectory . '/rollback.txt';

        $this->expectException(UploadStorageException::class);

        try {
            $storage->publishSnapshot(
                $snapshot,
                $this->targetDirectory,
                'rollback.txt',
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
            );
        } finally {
            self::assertFileDoesNotExist($target);
            self::assertSame([], $this->publicationTemporaryFiles());
        }
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
