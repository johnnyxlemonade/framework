<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Config;

use Lemonade\Framework\Core\Config\ApplicationConfigCache;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Core\Config\Exception\ApplicationConfigCacheException;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use PHPUnit\Framework\TestCase;

final class ApplicationConfigCacheTest extends TestCase
{
    private string $root = '';
    private string $source = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-application-config-cache-'
            . bin2hex(random_bytes(8));
        $this->source = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config.yaml';

        mkdir(dirname($this->source), 0700, true);
        mkdir($this->publicRoot(), 0700, true);
        file_put_contents($this->source, "shared: []\nhttp: []\ncli: []\n");
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
        unset($_ENV['CONFIG_CACHE_TEST']);
    }

    public function testRoundTripPreservesSupportedScalarAndArrayTypes(): void
    {
        $data = [
            'string' => "binary-\xFF\0-value",
            'integer' => PHP_INT_MIN,
            'float' => 1.0,
            'boolean' => true,
            'null' => null,
            'nested' => [2 => 'integer-key', '02' => 'string-key'],
        ];

        $this->write($data);
        $loaded = (new ApplicationConfigCache())->loadIfFresh($this->context(), 'http');

        self::assertNotNull($loaded);
        self::assertCount(1, $loaded);
        self::assertInstanceOf(ApplicationConfigCacheTestDefinition::class, $loaded[0]);
        self::assertSame($data, $loaded[0]->toArray());
    }

    public function testCacheAndKeyUsePrivatePermissionsAndNonExecutableFormat(): void
    {
        $this->write(['secret' => 'application-secret', 'password' => 'database-password']);

        self::assertSame(0600, fileperms($this->cacheFile()) & 0777);
        self::assertSame(0600, fileperms($this->keyFile()) & 0777);
        self::assertSame(0700, fileperms($this->cacheDirectory()) & 0777);
        self::assertSame(0700, fileperms(dirname($this->keyFile())) & 0777);
        self::assertNotSame(dirname($this->keyFile()), $this->cacheDirectory());

        $contents = file_get_contents($this->cacheFile());
        self::assertIsString($contents);
        self::assertJson($contents);
        self::assertStringNotContainsString('<?php', $contents);
    }

    public function testPhpPayloadInDataCacheIsNeverExecuted(): void
    {
        $this->createPrivateCacheDirectory();
        $marker = $this->root . DIRECTORY_SEPARATOR . 'executed';
        file_put_contents(
            $this->cacheFile(),
            '<?php file_put_contents(' . var_export($marker, true) . ", 'yes'); return [];",
        );
        chmod($this->cacheFile(), 0600);

        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));
        self::assertFileDoesNotExist($marker);
    }

    public function testLegacyPhpCacheIsIgnoredAndNeverExecuted(): void
    {
        $this->createPrivateCacheDirectory();
        $marker = $this->root . DIRECTORY_SEPARATOR . 'legacy-executed';
        file_put_contents(
            $this->legacyCacheFile(),
            '<?php file_put_contents(' . var_export($marker, true) . ", 'yes'); return [];",
        );

        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));
        self::assertFileDoesNotExist($marker);

        $this->write(['fresh' => true]);
        self::assertFileExists($this->cacheFile());
        self::assertFileExists($this->legacyCacheFile());
        self::assertFileDoesNotExist($marker);
    }

    public function testChangedBodyOrMacIsRejected(): void
    {
        $this->write(['value' => 'trusted']);
        $original = $this->readEnvelope();
        self::assertIsString($original['body']);
        $changedBody = $original;
        $changedBody['body'] .= 'A';
        $this->writeEnvelope($changedBody);
        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));

        $changedMac = $original;
        $changedMac['mac'] = str_repeat('0', 64);
        $this->writeEnvelope($changedMac);
        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));
    }

    public function testAuthenticatedUnknownVersionAndInvalidStructureAreRejected(): void
    {
        $this->write(['value' => 'trusted']);
        $envelope = $this->readEnvelope();
        $body = $this->readBody($envelope);
        $body['version'] = 999;
        $this->writeSignedBody($body);

        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));

        $body['version'] = 3;
        $body['payload'] = ['type' => 'object', 'value' => 'forbidden'];
        $this->writeSignedBody($body);

        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));
    }

    public function testCorruptedAndOversizedFilesAreCacheMisses(): void
    {
        $this->createPrivateCacheDirectory();
        file_put_contents($this->cacheFile(), '{not-json');
        chmod($this->cacheFile(), 0600);
        $cache = new ApplicationConfigCache(maxFileBytes: 1_024);

        self::assertNull($cache->loadIfFresh($this->context(), 'http'));

        file_put_contents($this->cacheFile(), str_repeat('x', 1_025));
        chmod($this->cacheFile(), 0600);

        self::assertNull($cache->loadIfFresh($this->context(), 'http'));
    }

    public function testKeyRotationInvalidatesExistingCache(): void
    {
        $this->write(['value' => 'before-rotation']);
        file_put_contents($this->keyFile(), random_bytes(32));
        chmod($this->keyFile(), 0600);

        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));
    }

    public function testEnvironmentAndManifestChangesInvalidateCache(): void
    {
        $_ENV['CONFIG_CACHE_TEST'] = 'first';
        $this->write(['value' => 'tracked'], ['CONFIG_CACHE_TEST']);
        self::assertNotNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));

        $_ENV['CONFIG_CACHE_TEST'] = 'second';
        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));

        $_ENV['CONFIG_CACHE_TEST'] = 'first';
        file_put_contents($this->source, "shared: []\nhttp: [Changed]\ncli: []\n");
        self::assertNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));
    }

    public function testConcurrentReadersNeverObservePartialPublication(): void
    {
        $this->write(['generation' => 0]);
        $pid = pcntl_fork();

        if ($pid === -1) {
            self::fail('Unable to fork config cache writer.');
        }

        if ($pid === 0) {
            try {
                for ($generation = 1; $generation <= 50; ++$generation) {
                    $this->write(['generation' => $generation]);
                }

                exit(0);
            } catch (\Throwable) {
                exit(1);
            }
        }

        $cache = new ApplicationConfigCache();

        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $loaded = $cache->loadIfFresh($this->context(), 'http');
            self::assertNotNull($loaded);
            self::assertCount(1, $loaded);
            self::assertIsInt($loaded[0]->toArray()['generation'] ?? null);
        }

        pcntl_waitpid($pid, $status);
        self::assertTrue(pcntl_wifexited($status));
        self::assertSame(0, pcntl_wexitstatus($status));
    }

    public function testConcurrentProcessesInitializeOneUsableIntegrityKey(): void
    {
        $children = [];

        for ($process = 0; $process < 4; ++$process) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                self::fail('Unable to fork config cache key initializer.');
            }

            if ($pid === 0) {
                try {
                    $this->write(['process' => $process]);
                    exit(0);
                } catch (\Throwable) {
                    exit(1);
                }
            }

            $children[] = $pid;
        }

        foreach ($children as $child) {
            pcntl_waitpid($child, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        self::assertSame(32, filesize($this->keyFile()));
        self::assertNotNull((new ApplicationConfigCache())->loadIfFresh($this->context(), 'http'));
    }

    public function testTraversalAndPublicStorageOverlapAreRejected(): void
    {
        $cache = new ApplicationConfigCache();

        try {
            $cache->write($this->context(), '../http', [], [], []);
            self::fail('Expected traversal rejection.');
        } catch (ApplicationConfigCacheException $exception) {
            self::assertStringContainsString('entrypoint', $exception->getMessage());
        }

        $unsafeContext = new ApplicationContext(
            Environment::Production,
            new Path($this->root, $this->root),
            DebugMode::disabled(),
        );

        $this->expectException(ApplicationConfigCacheException::class);
        $this->expectExceptionMessage('public web root');

        $cache->write($unsafeContext, 'http', [], [], []);
    }

    public function testSymlinkedCacheDirectoryAndFilesAreRejected(): void
    {
        $outside = $this->root . DIRECTORY_SEPARATOR . 'outside';
        mkdir($outside, 0700);
        mkdir($this->storageRoot(), 0700);
        symlink($outside, $this->storageRoot() . DIRECTORY_SEPARATOR . 'cache');

        try {
            $this->write(['value' => 'unsafe']);
            self::fail('Expected cache directory symlink rejection.');
        } catch (ApplicationConfigCacheException $exception) {
            self::assertStringContainsString('symbolic links', $exception->getMessage());
        }

        unlink($this->storageRoot() . DIRECTORY_SEPARATOR . 'cache');
        $this->write(['value' => 'safe']);
        $outsideFile = $outside . DIRECTORY_SEPARATOR . 'foreign';
        file_put_contents($outsideFile, 'foreign');
        unlink($this->cacheFile());
        symlink($outsideFile, $this->cacheFile());

        $this->expectException(ApplicationConfigCacheException::class);
        $this->expectExceptionMessage('safe regular file');

        (new ApplicationConfigCache())->loadIfFresh($this->context(), 'http');
    }

    public function testSymlinkedIntegrityKeyIsRejected(): void
    {
        $this->write(['value' => 'safe']);
        $outsideKey = $this->root . DIRECTORY_SEPARATOR . 'outside-key';
        file_put_contents($outsideKey, random_bytes(32));
        unlink($this->keyFile());
        symlink($outsideKey, $this->keyFile());

        $this->expectException(ApplicationConfigCacheException::class);
        $this->expectExceptionMessage('key path is unsafe');

        (new ApplicationConfigCache())->loadIfFresh($this->context(), 'http');
    }

    public function testAtomicWriteDoesNotFollowTargetSymlinkOutsideManagedStorage(): void
    {
        $this->write(['value' => 'safe']);
        $outside = $this->root . DIRECTORY_SEPARATOR . 'foreign-cache';
        file_put_contents($outside, 'foreign');
        unlink($this->cacheFile());
        symlink($outside, $this->cacheFile());

        try {
            $this->write(['value' => 'attacker-controlled-target']);
            self::fail('Expected target symlink rejection.');
        } catch (ApplicationConfigCacheException $exception) {
            self::assertStringContainsString('target path is unsafe', $exception->getMessage());
        }

        self::assertSame('foreign', file_get_contents($outside));
    }

    public function testCanonicalPublicRootOverlapThroughSymlinkIsRejected(): void
    {
        rmdir($this->publicRoot());
        mkdir($this->storageRoot(), 0700);
        symlink($this->storageRoot(), $this->publicRoot());

        $this->expectException(ApplicationConfigCacheException::class);
        $this->expectExceptionMessage('canonical path overlaps');

        $this->write(['value' => 'unsafe']);
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $envKeys
     */
    private function write(array $data, array $envKeys = []): void
    {
        (new ApplicationConfigCache())->write(
            context: $this->context(),
            entrypoint: 'http',
            definitions: [ApplicationConfigCacheTestDefinition::fromArrayData($data)],
            sourceFiles: [$this->source],
            envKeys: $envKeys,
        );
    }

    private function context(): ApplicationContext
    {
        return new ApplicationContext(
            Environment::Production,
            new Path($this->root, $this->publicRoot()),
            DebugMode::disabled(),
        );
    }

    private function publicRoot(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . 'public';
    }

    private function storageRoot(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . 'storage';
    }

    private function cacheDirectory(): string
    {
        return $this->storageRoot()
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'framework'
            . DIRECTORY_SEPARATOR . 'config';
    }

    private function cacheFile(): string
    {
        return $this->cacheDirectory() . DIRECTORY_SEPARATOR . 'application-http.cache';
    }

    private function legacyCacheFile(): string
    {
        return $this->cacheDirectory() . DIRECTORY_SEPARATOR . 'application-http.php';
    }

    private function keyFile(): string
    {
        return $this->storageRoot()
            . DIRECTORY_SEPARATOR . 'writable'
            . DIRECTORY_SEPARATOR . 'config-cache'
            . DIRECTORY_SEPARATOR . 'application-config-cache.key';
    }

    private function createPrivateCacheDirectory(): void
    {
        mkdir($this->cacheDirectory(), 0700, true);
        chmod($this->storageRoot() . DIRECTORY_SEPARATOR . 'cache', 0700);
        chmod($this->storageRoot() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'framework', 0700);
        chmod($this->cacheDirectory(), 0700);
    }

    /** @return array<string, mixed> */
    private function readEnvelope(): array
    {
        $contents = file_get_contents($this->cacheFile());
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    private function readBody(array $envelope): array
    {
        self::assertIsString($envelope['body'] ?? null);
        $body = base64_decode($envelope['body'], true);
        self::assertIsString($body);
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @param array<string, mixed> $body */
    private function writeSignedBody(array $body): void
    {
        $encodedBody = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $localKey = file_get_contents($this->keyFile());
        self::assertIsString($localKey);
        $key = hash_hkdf('sha256', $localKey, 32, 'lemonade.application-config-cache.v2');
        $this->writeEnvelope([
            'body' => base64_encode($encodedBody),
            'mac' => hash_hmac('sha256', $encodedBody, $key),
        ]);
    }

    /** @param array<string, mixed> $envelope */
    private function writeEnvelope(array $envelope): void
    {
        file_put_contents(
            $this->cacheFile(),
            json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        chmod($this->cacheFile(), 0600);
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

final class ApplicationConfigCacheTestDefinition extends AbstractConfigDefinition
{
    public static function moduleKey(): string
    {
        return 'application_config_cache_test';
    }
}
