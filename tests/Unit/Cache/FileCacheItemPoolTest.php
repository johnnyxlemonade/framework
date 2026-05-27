<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use DateTimeImmutable;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Lemonade\Framework\Cache\Store\CacheItem;
use Lemonade\Framework\Cache\Store\FileCacheItemPool;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;

final class FileCacheItemPoolTest extends TestCase
{
    private string $root;
    private string $cacheDir;
    private FileCacheItemPool $pool;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-file-cache-' . uniqid('', true);
        $this->cacheDir = $this->root . DIRECTORY_SEPARATOR . 'cache';
        $this->pool = new FileCacheItemPool($this->cacheDir, new DirectoryManager());
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testSaveCreatesCacheDirectoryAndStoresPayloadOnDisk(): void
    {
        $item = (new CacheItem('alpha'))->set('A');

        self::assertTrue($this->pool->save($item));
        self::assertDirectoryExists($this->cacheDir);
        self::assertNotEmpty($this->cacheFiles());
    }

    public function testGetItemAfterSaveReturnsHitWithOriginalValueAndHasItemReflectsIt(): void
    {
        $this->pool->save((new CacheItem('k'))->set(['x' => 1]));

        $item = $this->pool->getItem('k');
        self::assertTrue($item->isHit());
        self::assertSame(['x' => 1], $item->get());
        self::assertTrue($this->pool->hasItem('k'));
    }

    public function testDeleteItemAndDeleteItems(): void
    {
        $this->pool->save((new CacheItem('a'))->set('1'));
        $this->pool->save((new CacheItem('b'))->set('2'));
        $this->pool->save((new CacheItem('c'))->set('3'));

        self::assertTrue($this->pool->deleteItem('a'));
        self::assertFalse($this->pool->hasItem('a'));

        self::assertTrue($this->pool->deleteItems(['b', 'c']));
        self::assertFalse($this->pool->hasItem('b'));
        self::assertFalse($this->pool->hasItem('c'));
    }

    public function testClearDeletesAllCachePhpFiles(): void
    {
        $this->pool->save((new CacheItem('a'))->set('1'));
        $this->pool->save((new CacheItem('b'))->set('2'));

        self::assertTrue($this->pool->clear());
        self::assertSame([], $this->cacheFiles());
    }

    public function testSaveDeferredAndCommit(): void
    {
        self::assertTrue($this->pool->saveDeferred((new CacheItem('d'))->set('x')));
        self::assertFalse($this->pool->hasItem('d'));
        self::assertTrue($this->pool->commit());
        self::assertTrue($this->pool->hasItem('d'));
    }

    public function testExpiredItemReturnsMissAndDeletesCacheFile(): void
    {
        $this->pool->save((new CacheItem('exp', 'v', true))->expiresAt(new DateTimeImmutable('-1 second')));
        $filesBefore = $this->cacheFiles();
        self::assertNotSame([], $filesBefore);

        $item = $this->pool->getItem('exp');
        self::assertFalse($item->isHit());

        $filesAfter = $this->cacheFiles();
        self::assertSame([], $filesAfter);
    }

    public function testPayloadWithDifferentKeyIsIgnoredAsMiss(): void
    {
        $this->pool->save((new CacheItem('k'))->set('v'));
        $file = $this->firstCacheFilePath();
        file_put_contents(
            $file,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['key' => 'other', 'expires_at' => null, 'value' => 's:1:\"v\";'];\n",
        );

        self::assertFalse($this->pool->getItem('k')->isHit());
    }

    public function testInvalidPayloadIsIgnoredAsMiss(): void
    {
        $this->pool->save((new CacheItem('bad'))->set('v'));
        $file = $this->firstCacheFilePath();
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\nreturn 'invalid';\n");

        self::assertFalse($this->pool->getItem('bad')->isHit());
    }

    public function testCorruptedSerializedValueBecomesMissAndFileIsDeleted(): void
    {
        $this->pool->save((new CacheItem('ser'))->set('v'));
        $file = $this->firstCacheFilePath();
        file_put_contents(
            $file,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['key' => 'ser', 'expires_at' => null, 'value' => 'not-serialized'];\n",
        );

        self::assertFalse($this->pool->getItem('ser')->isHit());
        self::assertFileDoesNotExist($file);
    }

    public function testSaveReturnsFalseForForeignCacheItemImplementation(): void
    {
        self::assertFalse($this->pool->save(new FilePoolForeignCacheItem('foreign')));
    }

    public function testInvalidCacheKeyThrows(): void
    {
        $this->expectException(InvalidCacheKeyException::class);
        $this->pool->getItem('bad:key');
    }

    private function firstCacheFilePath(): string
    {
        $files = $this->cacheFiles();

        self::assertNotEmpty($files);
        $file = reset($files);
        self::assertIsString($file);

        return $file;
    }

    /**
     * @return list<string>
     */
    private function cacheFiles(): array
    {
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            return [];
        }

        return $files;
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}

final class FilePoolForeignCacheItem implements CacheItemInterface
{
    public function __construct(private readonly string $key) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return null;
    }

    public function isHit(): bool
    {
        return false;
    }

    public function set(mixed $value): static
    {
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
