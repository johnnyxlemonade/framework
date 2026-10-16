<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use Lemonade\Framework\Session\Storage\FileSessionStorage;
use Lemonade\Framework\Session\Storage\SessionCookie;
use PHPUnit\Framework\TestCase;

final class FileSessionStorageTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $_COOKIE = [];
        $_SERVER = [];
        $this->directory = sys_get_temp_dir() . '/lemonade-file-session-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_SERVER = [];

        if (is_dir($this->directory)) {
            $entries = scandir($this->directory);

            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        unlink($this->directory . DIRECTORY_SEPARATOR . $entry);
                    }
                }
            }

            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function testMissingAndExpiredCookieIdentitiesAreNeverReused(): void
    {
        $missingId = str_repeat('a', 64);
        $_COOKIE['TEST_SESSION'] = $missingId;
        $emissions = [];
        $storage = $this->storage($emissions);

        $storage->start();

        self::assertNotSame($missingId, $this->lastCookieValue($emissions));

        $expiredId = str_repeat('b', 64);
        $this->writeFixture($expiredId, ['name' => 'expired'], time() - 61);
        $_COOKIE['TEST_SESSION'] = $expiredId;
        $emissions = [];
        $storage = $this->storage($emissions);

        $storage->start();

        self::assertNotSame($expiredId, $this->lastCookieValue($emissions));
        self::assertFileDoesNotExist($this->file($expiredId));
    }

    public function testInvalidCookieIdentityIsRejectedEvenWhenItsFileExists(): void
    {
        $invalidId = str_repeat('A', 64);
        $this->writeFixture($invalidId, ['name' => 'Ada'], time());
        $_COOKIE['TEST_SESSION'] = $invalidId;
        $emissions = [];
        $storage = $this->storage($emissions);

        $storage->start();

        self::assertNotSame($invalidId, $this->lastCookieValue($emissions));
        self::assertNull($storage->get('name'));
    }

    public function testReadOnlyStartRefreshesStorageAndPersistentCookieRetention(): void
    {
        $sessionId = str_repeat('c', 64);
        $oldModifiedAt = time() - 20;
        $this->writeFixture($sessionId, ['name' => 'Ada'], $oldModifiedAt);
        $_COOKIE['TEST_SESSION'] = $sessionId;
        $emissions = [];
        $storage = $this->storage($emissions);
        $startedAt = time();

        $storage->start();

        clearstatcache(true, $this->file($sessionId));
        self::assertSame('Ada', $storage->get('name'));
        self::assertGreaterThan($oldModifiedAt, filemtime($this->file($sessionId)));
        self::assertSame($sessionId, $this->lastCookieValue($emissions));
        self::assertGreaterThanOrEqual($startedAt + 60, $this->lastCookieOptions($emissions)['expires']);
        self::assertSame(0700, fileperms($this->directory) & 0777);
        self::assertSame(0600, fileperms($this->file($sessionId)) & 0777);
    }

    public function testStoredNullRemainsDistinctFromAMissingKey(): void
    {
        $emissions = [];
        $storage = $this->storage($emissions);

        $storage->set('nullable', null);

        self::assertTrue($storage->has('nullable'));
        self::assertNull($storage->get('nullable', 'fallback'));
        self::assertSame('fallback', $storage->get('missing', 'fallback'));
    }

    public function testClearKeepsIdentityWhileDestroyRemovesItAndAllowsRestart(): void
    {
        $emissions = [];
        $storage = $this->storage($emissions);
        $storage->set('name', 'Ada');
        $sessionId = $this->lastCookieValue($emissions);

        $storage->clear();

        self::assertFileExists($this->file($sessionId));
        self::assertSame([], unserialize((string) file_get_contents($this->file($sessionId))));
        self::assertSame($sessionId, $this->lastCookieValue($emissions));

        $storage->destroy();

        self::assertFileDoesNotExist($this->file($sessionId));
        self::assertSame('', $this->lastCookieValue($emissions));
        self::assertLessThan(time(), $this->lastCookieOptions($emissions)['expires']);
        self::assertFalse($storage->started());

        $storage->start();

        self::assertNotSame($sessionId, $this->lastCookieValue($emissions));
    }

    public function testRegeneratePreservesPayloadAndHonorsOldRecordPolicy(): void
    {
        $emissions = [];
        $storage = $this->storage($emissions);
        $storage->set('name', 'Ada');
        $originalId = $this->lastCookieValue($emissions);

        $storage->regenerate(false);
        $retainedOldId = $this->lastCookieValue($emissions);

        self::assertNotSame($originalId, $retainedOldId);
        self::assertFileExists($this->file($originalId));
        self::assertSame('Ada', $storage->get('name'));

        $storage->regenerate(true);
        $currentId = $this->lastCookieValue($emissions);

        self::assertNotSame($retainedOldId, $currentId);
        self::assertFileDoesNotExist($this->file($retainedOldId));
        self::assertFileExists($this->file($originalId));
        self::assertSame('Ada', $storage->get('name'));
    }

    public function testOpportunisticGarbageCollectionProcessesAtMostConfiguredBatch(): void
    {
        $expiredIds = [];

        for ($index = 0; $index < 5; ++$index) {
            $sessionId = hash('sha256', 'expired-' . $index);
            $expiredIds[] = $sessionId;
            $this->writeFixture($sessionId, ['index' => $index], time() - 61);
        }

        $emissions = [];
        $storage = $this->storage(
            emissions: $emissions,
            garbageCollectionPolicy: new SessionGarbageCollectionPolicy(1, 1),
            gcBatchSize: 2,
        );

        $storage->start();

        $remainingAfterOneBatch = $this->existingFiles($expiredIds);
        self::assertCount(3, $remainingAfterOneBatch);

        for ($request = 0; $request < 4; ++$request) {
            $emissions = [];
            $this->storage(
                emissions: $emissions,
                garbageCollectionPolicy: new SessionGarbageCollectionPolicy(1, 1),
                gcBatchSize: 2,
            )->start();
        }

        self::assertSame([], $this->existingFiles($expiredIds));
    }

    public function testRecordRemovedBetweenReadAndTouchGetsFreshIdentity(): void
    {
        $sessionId = str_repeat('d', 64);
        $this->writeFixture($sessionId, ['name' => 'Ada'], time());
        $emissions = [];
        $storage = $this->storage($emissions);
        $reflection = new \ReflectionObject($storage);
        $read = $reflection->getMethod('readSessionFile');
        $resume = $reflection->getMethod('resumeSession');
        $data = $read->invoke($storage, $sessionId);

        unlink($this->file($sessionId));
        $resume->invoke($storage, $sessionId, $data);

        $replacementId = $this->readPrivateProperty($storage, 'sessionId');

        self::assertIsString($replacementId);
        self::assertNotSame($sessionId, $replacementId);
        self::assertFileExists($this->file($replacementId));
        self::assertSame([], unserialize((string) file_get_contents($this->file($replacementId))));
    }

    public function testGarbageCollectionRemovesOnlyAbandonedTemporaryFiles(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }

        $abandoned = $this->directory . '/.session-write-abandoned';
        $active = $this->directory . '/.session-write-active';
        file_put_contents($abandoned, 'old');
        file_put_contents($active, 'new');
        touch($abandoned, time() - 301);

        $emissions = [];
        $this->storage(
            emissions: $emissions,
            garbageCollectionPolicy: new SessionGarbageCollectionPolicy(1, 1),
            gcBatchSize: 10,
        )->start();

        self::assertFileDoesNotExist($abandoned);
        self::assertFileExists($active);
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     */
    private function storage(
        array &$emissions,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
        int $gcBatchSize = 100,
    ): FileSessionStorage {
        return new FileSessionStorage(
            directory: $this->directory,
            lifetimeSeconds: 60,
            cookieName: 'TEST_SESSION',
            gcBatchSize: $gcBatchSize,
            cookie: $this->cookie($emissions),
            garbageCollectionPolicy: $garbageCollectionPolicy ?? new SessionGarbageCollectionPolicy(0, 1),
        );
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     */
    private function cookie(array &$emissions): SessionCookie
    {
        return new SessionCookie(
            'TEST_SESSION',
            60,
            SessionCookieSameSite::Lax,
            static function (string $name, string $value, array $options) use (&$emissions): bool {
                $emissions[] = [
                    'name' => $name,
                    'value' => $value,
                    'options' => $options,
                ];

                return true;
            },
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeFixture(string $sessionId, array $data, int $modifiedAt): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }

        file_put_contents($this->file($sessionId), serialize($data));
        touch($this->file($sessionId), $modifiedAt);
    }

    private function file(string $sessionId): string
    {
        return $this->directory . '/sess_' . $sessionId;
    }

    /**
     * @param list<string> $sessionIds
     * @return list<string>
     */
    private function existingFiles(array $sessionIds): array
    {
        return array_values(array_filter(
            $sessionIds,
            fn(string $sessionId): bool => is_file($this->file($sessionId)),
        ));
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     */
    private function lastCookieValue(array $emissions): string
    {
        if ($emissions === []) {
            self::fail('Expected the session cookie to be emitted.');
        }

        return $emissions[array_key_last($emissions)]['value'];
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     * @return array<string, int|string|bool>
     */
    private function lastCookieOptions(array $emissions): array
    {
        if ($emissions === []) {
            self::fail('Expected the session cookie to be emitted.');
        }

        return $emissions[array_key_last($emissions)]['options'];
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionObject($object);

        return $reflection->getProperty($property)->getValue($object);
    }
}
