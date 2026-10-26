<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Generator;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Contract\LockManagerInterface;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\Exception\ImageDecodeException;
use Lemonade\Framework\Image\Exception\ImageEncodeException;
use Lemonade\Framework\Image\Exception\ImageTransformException;
use Lemonade\Framework\Image\Exception\ImageWriteException;
use Lemonade\Framework\Image\ImageVariantCache;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ImageVariantCacheTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-cache-' . uniqid('', true);
        mkdir($this->root . '/public', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testMissPublishesAndNextHitDoesNotGenerate(): void
    {
        $cache = $this->cache(lock: new LockManager(new DirectoryManager()));
        $asset = $this->asset();
        $definition = $this->definition();
        $calls = 0;
        $reference = $cache->remember(
            $asset,
            $definition,
            function () use (&$calls, $definition): EncodedImage {
                $calls++;

                return $this->encoded($definition);
            },
        );

        self::assertSame(1, $calls);
        self::assertFileExists($this->resolver()->variantPath($asset, $definition));
        $cached = $cache->remember(
            $asset,
            $definition,
            function () use (&$calls, $definition): EncodedImage {
                $calls++;

                return $this->encoded($definition, 'other');
            },
        );
        self::assertSame($reference->publicPath(), $cached->publicPath());
        self::assertSame(1, $calls);
    }

    public function testPublishedVariantUsesExplicitPublicPermissions(): void
    {
        $cache = $this->cache(lock: new LockManager(new DirectoryManager()));
        $asset = $this->asset();
        $definition = $this->definition();
        $previousUmask = umask(0000);

        try {
            $cache->remember(
                $asset,
                $definition,
                fn (): EncodedImage => $this->encoded($definition),
            );
        } finally {
            umask($previousUmask);
        }

        $target = $this->resolver()->variantPath($asset, $definition);
        self::assertSame(0644, fileperms($target) & 0777);
        self::assertSame(0755, fileperms(dirname($target)) & 0777);
        self::assertFileDoesNotExist($target . '.lock');
        self::assertFileExists($this->resolver()->variantLockPath($asset, $definition));
    }

    public function testForgetRemovesOnlyExactAssetSourceVersion(): void
    {
        $cache = $this->cache();
        $definition = $this->definition();
        $removed = $this->asset();
        $otherVersion = $this->asset(sourceVersion: 'v2');
        $otherAsset = $this->asset(assetId: 'other-asset');

        foreach ([$removed, $otherVersion, $otherAsset] as $asset) {
            $cache->remember(
                $asset,
                $definition,
                fn (): EncodedImage => $this->encoded($definition),
            );
        }

        $cache->forget($removed);

        self::assertDirectoryDoesNotExist($this->resolver()->variantDirectory($removed));
        self::assertFileExists($this->resolver()->variantPath($otherVersion, $definition));
        self::assertFileExists($this->resolver()->variantPath($otherAsset, $definition));
    }

    public function testSecondCheckReturnsTargetCreatedWhileWaitingForLock(): void
    {
        $asset = $this->asset();
        $definition = $this->definition();
        $target = $this->resolver()->variantPath($asset, $definition);
        $lock = new CacheLockManager();
        $lock->beforeCallback = static function () use ($target): void {
            file_put_contents($target, 'first-consumer');
        };
        $calls = 0;
        $reference = $this->cache(lock: $lock)->remember(
            $asset,
            $definition,
            function () use (&$calls, $definition): EncodedImage {
                $calls++;

                return $this->encoded($definition);
            },
        );

        self::assertSame(0, $calls);
        self::assertSame(
            $this->resolver()->reference($asset, $definition)->publicPath(),
            $reference->publicPath(),
        );
        self::assertSame('first-consumer', file_get_contents($target));
    }

    public function testWriteFailureRemovesTemporaryFileAndNeverPublishesTarget(): void
    {
        $directory = new CacheDirectoryManager();
        $directory->writeFailure = true;
        $this->assertWriteFailureLeavesNoArtifacts($this->cache(directory: $directory));
    }

    public function testPublishFailureRemovesTemporaryFileAndNeverPublishesPartialTarget(): void
    {
        $directory = new CacheDirectoryManager();
        $directory->moveFailure = true;
        $this->assertWriteFailureLeavesNoArtifacts($this->cache(directory: $directory));
    }

    public function testCleanupFailureDoesNotReplacePrimaryWriteFailure(): void
    {
        $directory = new CacheDirectoryManager();
        $directory->writeFailure = true;
        $directory->deleteFailure = true;
        try {
            $this->cache(directory: $directory)->remember(
                $this->asset(),
                $this->definition(),
                fn (): EncodedImage => $this->encoded($this->definition()),
            );
            self::fail('Expected image write failure.');
        } catch (ImageWriteException $exception) {
            self::assertInstanceOf(FilesystemException::class, $exception->getPrevious());
        }
    }

    public function testGenericLockFailureMapsToImageWriteException(): void
    {
        $lock = new CacheLockManager();
        $lock->failure = new FilesystemException('lock failure');
        $this->expectException(ImageWriteException::class);
        $this->cache(lock: $lock)->remember(
            $this->asset(),
            $this->definition(),
            fn (): EncodedImage => $this->encoded($this->definition()),
        );
    }

    public function testGenericPublishFailureMapsToImageWriteException(): void
    {
        $directory = new CacheDirectoryManager();
        $directory->moveFailure = true;
        $this->expectException(ImageWriteException::class);
        $this->cache(directory: $directory)->remember(
            $this->asset(),
            $this->definition(),
            fn (): EncodedImage => $this->encoded($this->definition()),
        );
    }

    public function testTypedRendererFailuresArePreservedThroughLock(): void
    {
        foreach ([
            new ImageDecodeException('decode'),
            new ImageTransformException('transform'),
            new ImageEncodeException('encode'),
        ] as $failure) {
            try {
                $this->cache()->remember(
                    $this->asset(),
                    $this->definition(),
                    static function () use ($failure): EncodedImage {
                        throw $failure;
                    },
                );
                self::fail('Expected renderer failure.');
            } catch (Throwable $exception) {
                self::assertSame($failure, $exception);
            }
        }
    }

    private function assertWriteFailureLeavesNoArtifacts(ImageVariantCache $cache): void
    {
        $asset = $this->asset();
        $definition = $this->definition();
        $target = $this->resolver()->variantPath($asset, $definition);
        try {
            $cache->remember(
                $asset,
                $definition,
                fn (): EncodedImage => $this->encoded($definition),
            );
            self::fail('Expected image write failure.');
        } catch (ImageWriteException) {
            self::assertFileDoesNotExist($target);
            self::assertSame([], glob($target . '.tmp-*'));
        }
    }

    private function cache(?CacheDirectoryManager $directory = null, ?LockManagerInterface $lock = null): ImageVariantCache
    {
        $directory ??= new CacheDirectoryManager();
        $lock ??= new CacheLockManager();

        return new ImageVariantCache(
            new Filesystem(
                $directory,
                new FileManager(),
                $lock,
            ),
            $this->resolver(),
        );
    }

    private function resolver(): ImageVariantPathResolver
    {
        return new ImageVariantPathResolver(
            new ApplicationContext(
                Environment::Testing,
                new Path($this->root, $this->root . '/public'),
                DebugMode::disabled(),
            ),
            new DirectoryPathGenerator(),
        );
    }

    private function asset(string $assetId = 'asset', string $sourceVersion = 'v1'): ImageAsset
    {
        return new ImageAsset(
            $assetId,
            new ImageOriginalReference(
                ImageOriginalStorage::Storage,
                'source.jpg',
                $sourceVersion,
                ImageFormat::Jpeg,
                new ImageDimensions(100, 50),
            ),
        );
    }

    private function definition(): ImageVariantDefinition
    {
        return new ImageVariantDefinition(
            new ImageDimensions(64, 64),
            ImageFormat::Webp,
            ImageQuality::fromInt(80),
        );
    }

    private function encoded(ImageVariantDefinition $definition, string $contents = 'bytes'): EncodedImage
    {
        return new EncodedImage(
            $contents,
            ImageFormat::Webp,
            $definition->dimensions(),
        );
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        foreach ($entries !== false ? $entries : [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $child = $path . '/' . $name;
            if (is_dir($child)) {
                $this->remove($child);

                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}

final class CacheDirectoryManager implements DirectoryManagerInterface
{
    public bool $writeFailure = false;
    public bool $moveFailure = false;
    public bool $deleteFailure = false;
    private DirectoryManager $delegate;
    public function __construct()
    {
        $this->delegate = new DirectoryManager();
    }

    public function create(string $path, int $mode = 0775): void { $this->delegate->create($path, $mode); }
    public function delete(string $path): void
    {
        if ($this->deleteFailure) {
            throw new FilesystemException('cleanup failure');
        }

        $this->delegate->delete($path);
    }
    public function copy(string $src, string $dst, bool $overwrite = false): void { $this->delegate->copy($src, $dst, $overwrite); }
    public function write(string $file, string $data, ?int $mode = 0666): void
    {
        $this->delegate->write($file, $data, $mode);
        if ($this->writeFailure) {
            throw new FilesystemException('write failure');
        }
    }
    public function move(string $source, string $target): void
    {
        if ($this->moveFailure) {
            throw new FilesystemException('publish failure');
        }

        $this->delegate->move($source, $target);
    }

    public function stream(string $path, bool $recursive = true): Generator
    {
        return $this->delegate->stream($path, $recursive);
    }

    public function tree(string $path, bool $recursive = true): Generator
    {
        return $this->delegate->tree($path, $recursive);
    }

    public function find(string $pattern, string $path): Generator
    {
        return $this->delegate->find($pattern, $path);
    }
}

final class CacheLockManager implements LockManagerInterface
{
    public ?Throwable $failure = null;
    public ?\Closure $beforeCallback = null;

    public function lock(string $file, callable $callback): mixed
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->beforeCallback?->__invoke();

        return $callback();
    }
}
