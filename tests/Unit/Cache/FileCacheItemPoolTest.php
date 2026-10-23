<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use DateTimeImmutable;
use Lemonade\Framework\Cache\Exception\CacheStorageException;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Lemonade\Framework\Cache\Store\CacheItem;
use Lemonade\Framework\Cache\Store\FileCacheItemPool;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;

final class FileCacheItemPoolTest extends TestCase
{
    private const string SECRET = 'test-file-cache-secret';
    private const string NAMESPACE = 'test-pool';

    private string $root = '';
    private string $cacheDir = '';
    private ?FileCacheItemPool $subject = null;

    protected function setUp(): void
    {
        FileCacheWakeupPayload::$calls = 0;
        $this->root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-file-cache-'
            . bin2hex(random_bytes(8));
        $this->cacheDir = $this->storageRoot() . DIRECTORY_SEPARATOR . 'cache';
        $this->subject = $this->pool();
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    private function subject(): FileCacheItemPool
    {
        self::assertNotNull($this->subject);

        return $this->subject;
    }

    public function testStoresAuthenticatedDataFilesWithPrivatePermissions(): void
    {
        self::assertTrue($this->subject()->save((new CacheItem('alpha'))->set('A')));

        $file = $this->cacheFile('alpha');

        self::assertDirectoryExists($this->cacheDir);
        self::assertFileExists($file);
        self::assertStringEndsWith('.cache', $file);
        self::assertSame(0700, fileperms($this->cacheDir) & 0777);
        self::assertSame(0600, fileperms($file) & 0777);

        $contents = file_get_contents($file);
        self::assertIsString($contents);
        self::assertJson($contents);
        self::assertStringNotContainsString('<?php', $contents);
    }

    public function testOriginalTwoArgumentConstructionRemainsZeroConfiguration(): void
    {
        $directory = $this->root . DIRECTORY_SEPARATOR . 'standalone' . DIRECTORY_SEPARATOR . 'cache';
        $pool = new FileCacheItemPool($directory, new DirectoryManager());

        self::assertTrue($pool->save((new CacheItem('zero-config'))->set('value')));
        self::assertSame('value', $pool->getItem('zero-config')->get());
        self::assertFileExists($directory . DIRECTORY_SEPARATOR . '.lemonade-file-cache.key');
    }

    public function testRoundTripsScalarArrayDateTimeEnumAndObjectValues(): void
    {
        $date = new DateTimeImmutable('2026-10-23T12:34:56.123456+02:00');
        $object = new FileCacheValueObject('Ada', 42);
        $value = [
            'null' => null,
            'bool' => true,
            'int' => 7,
            'float' => 1.5,
            'string' => "binary\0value",
            'array' => ['nested' => [1, 2, 3]],
            'date' => $date,
            'enum' => FileCacheState::Ready,
            'object' => $object,
        ];

        self::assertTrue($this->subject()->save((new CacheItem('types'))->set($value)));

        $item = $this->subject()->getItem('types');

        self::assertTrue($item->isHit());
        self::assertEquals($value, $item->get());
        self::assertSame($date->format('Y-m-d H:i:s.uP'), $item->get()['date']->format('Y-m-d H:i:s.uP'));
        self::assertSame(FileCacheState::Ready, $item->get()['enum']);
        self::assertEquals($object, $item->get()['object']);
    }

    public function testLegitimateAuthenticatedObjectIsRestored(): void
    {
        self::assertTrue($this->subject()->save(
            (new CacheItem('object'))->set(new FileCacheWakeupPayload('trusted')),
        ));
        FileCacheWakeupPayload::$calls = 0;

        $item = $this->subject()->getItem('object');

        self::assertTrue($item->isHit());
        self::assertInstanceOf(FileCacheWakeupPayload::class, $item->get());
        self::assertSame('trusted', $item->get()->value);
        self::assertSame(1, FileCacheWakeupPayload::$calls);
    }

    public function testTamperedMacReturnsMissWithoutInvokingMagicMethods(): void
    {
        self::assertTrue($this->subject()->save(
            (new CacheItem('tampered'))->set(new FileCacheWakeupPayload('signed')),
        ));
        FileCacheWakeupPayload::$calls = 0;

        $file = $this->cacheFile('tampered');
        $envelope = $this->decodedJsonFile($file);
        $envelope['mac'] = str_repeat('0', 64);
        file_put_contents($file, json_encode($envelope, JSON_THROW_ON_ERROR));

        self::assertFalse($this->subject()->getItem('tampered')->isHit());
        self::assertSame(0, FileCacheWakeupPayload::$calls);
    }

    public function testTamperedPayloadReturnsMissWithoutInvokingMagicMethods(): void
    {
        self::assertTrue($this->subject()->save(
            (new CacheItem('payload'))->set(new FileCacheWakeupPayload('signed')),
        ));
        FileCacheWakeupPayload::$calls = 0;

        $file = $this->cacheFile('payload');
        $envelope = $this->decodedJsonFile($file);
        $body = base64_decode((string) $envelope['body'], true);
        self::assertIsString($body);
        $payload = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $payload['payload'] = base64_encode(serialize(new FileCacheWakeupPayload('forged')));
        $envelope['body'] = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        file_put_contents($file, json_encode($envelope, JSON_THROW_ON_ERROR));

        self::assertFalse($this->subject()->getItem('payload')->isHit());
        self::assertSame(0, FileCacheWakeupPayload::$calls);
    }

    public function testLegacyPhpPayloadIsNeverLoadedOrRemovedByPoolOperations(): void
    {
        self::assertTrue($this->subject()->save((new CacheItem('seed'))->set('value')));
        $marker = $this->root . DIRECTORY_SEPARATOR . 'executed';
        $legacy = $this->cacheDir . DIRECTORY_SEPARATOR . sha1('legacy') . '.php';
        file_put_contents(
            $legacy,
            '<?php file_put_contents(' . var_export($marker, true) . ", 'yes'); return [];",
        );

        self::assertFalse($this->subject()->getItem('legacy')->isHit());
        self::assertFileDoesNotExist($marker);
        self::assertTrue($this->subject()->clear());
        self::assertFileExists($legacy);
        self::assertFileDoesNotExist($marker);
    }

    public function testCorruptedAndOversizedFilesReturnMiss(): void
    {
        $limited = $this->pool(maxFileBytes: 1024);
        self::assertTrue($limited->save((new CacheItem('corrupt'))->set('value')));
        file_put_contents($this->cacheFile('corrupt'), '{not-json');

        self::assertFalse($limited->getItem('corrupt')->isHit());

        file_put_contents($this->cacheFile('corrupt'), str_repeat('x', 1025));

        self::assertFalse($limited->getItem('corrupt')->isHit());
    }

    public function testAuthenticatedButInvalidSerializationReturnsMiss(): void
    {
        $this->writeSignedPayload('invalid-serialization', 'not-serialized');

        self::assertFalse($this->subject()->getItem('invalid-serialization')->isHit());
    }

    public function testAuthenticatedEnvelopeStillRequiresMatchingVersionAndCacheKey(): void
    {
        $this->writeSignedPayload(
            key: 'wrong-key',
            serialized: serialize(new FileCacheWakeupPayload('forged')),
            envelopeKey: 'different-key',
        );
        FileCacheWakeupPayload::$calls = 0;

        self::assertFalse($this->subject()->getItem('wrong-key')->isHit());
        self::assertSame(0, FileCacheWakeupPayload::$calls);

        $this->writeSignedPayload(
            key: 'wrong-version',
            serialized: serialize(new FileCacheWakeupPayload('forged')),
            version: 2,
        );

        self::assertFalse($this->subject()->getItem('wrong-version')->isHit());
        self::assertSame(0, FileCacheWakeupPayload::$calls);
    }

    public function testSerializationDepthIsBoundedAfterAuthentication(): void
    {
        $limited = $this->pool(maxSerializationDepth: 3);
        $deep = ['one' => ['two' => ['three' => ['four' => 'value']]]];

        self::assertTrue($limited->save((new CacheItem('deep'))->set($deep)));
        self::assertFalse($limited->getItem('deep')->isHit());
    }

    public function testChangingApplicationSecretInvalidatesExistingContent(): void
    {
        $first = $this->pool(secret: 'first-secret');
        self::assertTrue($first->save((new CacheItem('rotated'))->set(new FileCacheWakeupPayload('old'))));
        FileCacheWakeupPayload::$calls = 0;

        $rotated = $this->pool(secret: 'second-secret');

        self::assertFalse($rotated->getItem('rotated')->isHit());
        self::assertSame(0, FileCacheWakeupPayload::$calls);
    }

    public function testReplacingLocalIntegrityKeyInvalidatesExistingContent(): void
    {
        $first = $this->pool(secret: null);
        self::assertTrue($first->save((new CacheItem('local-rotation'))->set(
            new FileCacheWakeupPayload('old'),
        )));
        file_put_contents($this->keyFile(), random_bytes(32));
        chmod($this->keyFile(), 0600);
        FileCacheWakeupPayload::$calls = 0;

        $rotated = $this->pool(secret: null);

        self::assertFalse($rotated->getItem('local-rotation')->isHit());
        self::assertSame(0, FileCacheWakeupPayload::$calls);
    }

    public function testLocalKeyIsCreatedOnceAndSharedByConcurrentProcesses(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the concurrency regression test.');
        }

        $gate = $this->root . DIRECTORY_SEPARATOR . 'gate';
        mkdir($this->root, 0700, true);
        $children = [];

        foreach (['first', 'second'] as $key) {
            $processId = pcntl_fork();

            if ($processId === -1) {
                self::fail('Unable to fork cache key initialization test.');
            }

            if ($processId === 0) {
                while (!is_file($gate)) {
                    usleep(1000);
                }

                $pool = $this->pool(secret: null);
                exit($pool->save((new CacheItem($key))->set($key)) ? 0 : 1);
            }

            $children[] = $processId;
        }

        file_put_contents($gate, 'go');

        foreach ($children as $processId) {
            $status = 0;
            pcntl_waitpid($processId, $status);
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        $keyFile = $this->keyFile();
        self::assertFileExists($keyFile);
        self::assertSame(32, filesize($keyFile));
        self::assertSame(0600, fileperms($keyFile) & 0777);

        $pool = $this->pool(secret: null);
        self::assertSame('first', $pool->getItem('first')->get());
        self::assertSame('second', $pool->getItem('second')->get());
    }

    public function testPoolsSharingDirectoryRemainIsolatedAndClearOnlyOwnFiles(): void
    {
        $first = $this->pool(namespace: 'first');
        $second = $this->pool(namespace: 'second');
        self::assertTrue($first->save((new CacheItem('same'))->set('first')));
        self::assertTrue($second->save((new CacheItem('same'))->set('second')));
        $unrelated = $this->cacheDir . DIRECTORY_SEPARATOR . 'unrelated.cache';
        file_put_contents($unrelated, 'data');

        self::assertSame('first', $first->getItem('same')->get());
        self::assertSame('second', $second->getItem('same')->get());
        self::assertTrue($first->clear());
        self::assertFalse($first->getItem('same')->isHit());
        self::assertSame('second', $second->getItem('same')->get());
        self::assertFileExists($unrelated);
    }

    public function testTraversalAndPublicRootOverlapAreRejected(): void
    {
        $this->expectException(CacheStorageException::class);

        new FileCacheItemPool(
            directory: $this->root . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public',
            directoryManager: new DirectoryManager(),
            storageRoot: $this->root,
            publicRoot: $this->root . DIRECTORY_SEPARATOR . 'public',
        );
    }

    public function testPublicRootContainmentIsRejected(): void
    {
        $this->expectException(CacheStorageException::class);

        new FileCacheItemPool(
            directory: $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'cache',
            directoryManager: new DirectoryManager(),
            storageRoot: $this->root,
            publicRoot: $this->root . DIRECTORY_SEPARATOR . 'public',
        );
    }

    public function testSymlinkCacheRootIsRejectedWithoutTouchingTarget(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $outside = $this->root . DIRECTORY_SEPARATOR . 'outside';
        $storage = $this->root . DIRECTORY_SEPARATOR . 'storage';
        mkdir($outside, 0700, true);
        mkdir($storage, 0700, true);
        symlink($outside, $storage . DIRECTORY_SEPARATOR . 'cache');

        try {
            new FileCacheItemPool(
                directory: $storage . DIRECTORY_SEPARATOR . 'cache',
                directoryManager: new DirectoryManager(),
                storageRoot: $storage,
            );
            self::fail('Expected symbolic-link cache root rejection.');
        } catch (CacheStorageException) {
            self::assertDirectoryExists($outside);
        }
    }

    public function testSymlinkPayloadReturnsMissAndNeverTouchesOutsideFile(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        self::assertTrue($this->subject()->save((new CacheItem('linked'))->set('inside')));
        $file = $this->cacheFile('linked');
        $outside = $this->root . DIRECTORY_SEPARATOR . 'outside-data';
        file_put_contents($outside, 'outside');
        unlink($file);
        symlink($outside, $file);

        self::assertFalse($this->subject()->getItem('linked')->isHit());
        self::assertSame('outside', file_get_contents($outside));
    }

    public function testSymlinkIntegrityKeyIsRejectedWithoutChangingItsTarget(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        mkdir(dirname($this->keyFile()), 0700, true);
        $outside = $this->root . DIRECTORY_SEPARATOR . 'outside-key';
        file_put_contents($outside, str_repeat('x', 32));
        symlink($outside, $this->keyFile());

        try {
            $pool = $this->pool(secret: null);
            $pool->save((new CacheItem('key-link'))->set('value'));
            self::fail('Expected symbolic-link integrity key rejection.');
        } catch (CacheStorageException) {
            self::assertSame(str_repeat('x', 32), file_get_contents($outside));
        }
    }

    public function testWritableStorageRootIsRestrictedBeforeUse(): void
    {
        mkdir($this->storageRoot(), 0777, true);
        chmod($this->storageRoot(), 0777);
        $pool = $this->pool();

        self::assertTrue($pool->save((new CacheItem('secured'))->set('value')));
        self::assertSame(0, fileperms($this->storageRoot()) & 0022);
    }

    public function testConcurrentReadsObserveOnlyCompletePublishedValues(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the concurrency regression test.');
        }

        self::assertTrue($this->subject()->save((new CacheItem('race'))->set(['version' => 'old'])));
        $processId = pcntl_fork();

        if ($processId === -1) {
            self::fail('Unable to fork atomic cache publication test.');
        }

        if ($processId === 0) {
            $writer = $this->pool();

            for ($iteration = 0; $iteration < 100; ++$iteration) {
                $value = ['version' => $iteration % 2 === 0 ? 'new-a' : 'new-b'];

                if (!$writer->save((new CacheItem('race'))->set($value))) {
                    exit(1);
                }
            }

            exit(0);
        }

        for ($iteration = 0; $iteration < 200; ++$iteration) {
            $item = $this->subject()->getItem('race');
            self::assertTrue($item->isHit());
            self::assertContains($item->get(), [
                ['version' => 'old'],
                ['version' => 'new-a'],
                ['version' => 'new-b'],
            ]);
        }

        $status = 0;
        pcntl_waitpid($processId, $status);
        self::assertSame(0, pcntl_wexitstatus($status));
    }

    public function testDeleteDeferredExpiryAndInvalidKeysRemainCompatible(): void
    {
        self::assertTrue($this->subject()->saveDeferred((new CacheItem('deferred'))->set('value')));
        self::assertFalse($this->subject()->hasItem('deferred'));
        self::assertTrue($this->subject()->commit());
        self::assertSame('value', $this->subject()->getItem('deferred')->get());

        self::assertTrue($this->subject()->save(
            (new CacheItem('expired', 'value', true))->expiresAt(new DateTimeImmutable('-1 second')),
        ));
        self::assertFalse($this->subject()->getItem('expired')->isHit());
        self::assertFileDoesNotExist($this->cacheFile('expired'));

        self::assertTrue($this->subject()->deleteItem('deferred'));
        self::assertFalse($this->subject()->hasItem('deferred'));

        $this->expectException(InvalidCacheKeyException::class);
        $this->subject()->getItem('bad:key');
    }

    public function testSaveReturnsFalseForForeignCacheItemImplementation(): void
    {
        self::assertFalse($this->subject()->save(new FilePoolForeignCacheItem('foreign')));
    }

    private function pool(
        ?string $secret = self::SECRET,
        string $namespace = self::NAMESPACE,
        int $maxFileBytes = 8_388_608,
        int $maxSerializationDepth = 64,
    ): FileCacheItemPool {
        return new FileCacheItemPool(
            directory: $this->cacheDir,
            directoryManager: new DirectoryManager(),
            applicationSecret: $secret,
            namespace: $namespace,
            keyFile: $this->keyFile(),
            storageRoot: $this->storageRoot(),
            publicRoot: $this->root . DIRECTORY_SEPARATOR . 'public',
            maxFileBytes: $maxFileBytes,
            maxSerializationDepth: $maxSerializationDepth,
        );
    }

    private function keyFile(): string
    {
        return $this->root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'keys'
            . DIRECTORY_SEPARATOR
            . 'file-cache.key';
    }

    private function storageRoot(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . 'storage';
    }

    private function cacheFile(string $key, string $namespace = self::NAMESPACE): string
    {
        $poolId = substr(hash('sha256', $namespace), 0, 24);
        $keyHash = hash('sha256', $poolId . "\0" . $key);

        return sprintf(
            '%s%slemonade-cache-v1-%s-%s.cache',
            $this->cacheDir,
            DIRECTORY_SEPARATOR,
            $poolId,
            $keyHash,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedJsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function writeSignedPayload(
        string $key,
        string $serialized,
        ?string $envelopeKey = null,
        int $version = 1,
    ): void {
        $poolId = substr(hash('sha256', self::NAMESPACE), 0, 24);
        $integrityKey = hash_hkdf(
            'sha256',
            self::SECRET,
            32,
            'lemonade.file-cache.pool.v1:' . $poolId,
        );
        $body = json_encode(
            [
                'version' => $version,
                'pool' => $poolId,
                'key' => base64_encode($envelopeKey ?? $key),
                'expires_at' => null,
                'payload' => base64_encode($serialized),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $envelope = json_encode(
            [
                'body' => base64_encode($body),
                'mac' => hash_hmac('sha256', $body, $integrityKey),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0700, true);
        }
        file_put_contents($this->cacheFile($key), $envelope);
        chmod($this->cacheFile($key), 0600);
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
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

final readonly class FileCacheValueObject
{
    public function __construct(
        public string $name,
        public int $number,
    ) {
    }
}

final class FileCacheWakeupPayload
{
    public static int $calls = 0;

    public function __construct(public string $value)
    {
    }

    public function __wakeup(): void
    {
        ++self::$calls;
    }
}

enum FileCacheState: string
{
    case Ready = 'ready';
}

final class FilePoolForeignCacheItem implements CacheItemInterface
{
    public function __construct(private readonly string $key)
    {
    }

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
