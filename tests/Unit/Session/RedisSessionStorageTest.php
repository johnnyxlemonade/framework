<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Cookie\SessionCookieSecure;
use Lemonade\Framework\Session\Storage\RedisSessionClientInterface;
use Lemonade\Framework\Session\Storage\RedisSessionStorage;
use Lemonade\Framework\Session\Storage\SessionCookie;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RedisSessionStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_COOKIE = [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_SERVER = [];

        parent::tearDown();
    }

    public function testMissingAndExpiredCookieIdentitiesAreNeverReused(): void
    {
        $backend = new SessionRedisBackendDouble();
        $missingId = str_repeat('a', 64);
        $_COOKIE['TEST_SESSION'] = $missingId;
        $emissions = [];

        $this->storage($backend, $emissions)->start();

        $newId = $this->lastCookieValue($emissions);
        self::assertNotSame($missingId, $newId);
        self::assertArrayHasKey('test:' . $newId, $backend->payloads);

        $expiredId = str_repeat('b', 64);
        $backend->payloads['test:' . $expiredId] = serialize(['name' => 'expired']);
        $backend->expiresAt['test:' . $expiredId] = time() - 1;
        $_COOKIE['TEST_SESSION'] = $expiredId;
        $emissions = [];

        $this->storage($backend, $emissions)->start();

        self::assertNotSame($expiredId, $this->lastCookieValue($emissions));
        self::assertArrayNotHasKey('test:' . $expiredId, $backend->payloads);
    }

    public function testInvalidCookieIdentityIsRejectedEvenWhenItsKeyExists(): void
    {
        $backend = new SessionRedisBackendDouble();
        $invalidId = str_repeat('A', 64);
        $backend->payloads['test:' . $invalidId] = serialize(['name' => 'Ada']);
        $backend->expiresAt['test:' . $invalidId] = time() + 60;
        $_COOKIE['TEST_SESSION'] = $invalidId;
        $emissions = [];
        $storage = $this->storage($backend, $emissions);

        $storage->start();

        self::assertNotSame($invalidId, $this->lastCookieValue($emissions));
        self::assertNull($storage->get('name'));
    }

    public function testReadOnlyStartRefreshesRedisAndPersistentCookieRetention(): void
    {
        $backend = new SessionRedisBackendDouble();
        $sessionId = str_repeat('c', 64);
        $backend->payloads['test:' . $sessionId] = serialize(['name' => 'Ada']);
        $backend->expiresAt['test:' . $sessionId] = time() + 10;
        $_COOKIE['TEST_SESSION'] = $sessionId;
        $emissions = [];
        $startedAt = time();
        $storage = $this->storage($backend, $emissions);

        $storage->start();

        self::assertSame('Ada', $storage->get('name'));
        self::assertGreaterThanOrEqual($startedAt + 60, $backend->expiresAt['test:' . $sessionId]);
        self::assertSame($sessionId, $this->lastCookieValue($emissions));
        self::assertGreaterThanOrEqual($startedAt + 60, $this->lastCookieOptions($emissions)['expires']);
    }

    public function testStoredNullRemainsDistinctFromAMissingKey(): void
    {
        $backend = new SessionRedisBackendDouble();
        $emissions = [];
        $storage = $this->storage($backend, $emissions);

        $storage->set('nullable', null);

        self::assertTrue($storage->has('nullable'));
        self::assertNull($storage->get('nullable', 'fallback'));
        self::assertSame('fallback', $storage->get('missing', 'fallback'));
    }

    public function testKeyLostBetweenReadAndTouchGetsFreshIdentity(): void
    {
        $backend = new SessionRedisBackendDouble();
        $oldId = str_repeat('d', 64);
        $backend->payloads['test:' . $oldId] = serialize(['name' => 'Ada']);
        $backend->expiresAt['test:' . $oldId] = time() + 60;
        $backend->removeOnNextExpire = true;
        $_COOKIE['TEST_SESSION'] = $oldId;
        $emissions = [];

        $this->storage($backend, $emissions)->start();

        self::assertNotSame($oldId, $this->lastCookieValue($emissions));
        self::assertArrayNotHasKey('test:' . $oldId, $backend->payloads);
    }

    public function testClearKeepsIdentityWhileDestroyRemovesItAndAllowsRestart(): void
    {
        $backend = new SessionRedisBackendDouble();
        $emissions = [];
        $storage = $this->storage($backend, $emissions);
        $storage->set('name', 'Ada');
        $sessionId = $this->lastCookieValue($emissions);

        $storage->clear();

        self::assertArrayHasKey('test:' . $sessionId, $backend->payloads);
        self::assertSame([], unserialize($backend->payloads['test:' . $sessionId]));

        $storage->destroy();

        self::assertArrayNotHasKey('test:' . $sessionId, $backend->payloads);
        self::assertSame('', $this->lastCookieValue($emissions));
        self::assertFalse($storage->started());

        $storage->start();

        self::assertNotSame($sessionId, $this->lastCookieValue($emissions));
    }

    public function testRegeneratePreservesPayloadAndHonorsOldKeyPolicy(): void
    {
        $backend = new SessionRedisBackendDouble();
        $emissions = [];
        $storage = $this->storage($backend, $emissions);
        $storage->set('name', 'Ada');
        $originalId = $this->lastCookieValue($emissions);

        $storage->regenerate(false);
        $retainedOldId = $this->lastCookieValue($emissions);

        self::assertArrayHasKey('test:' . $originalId, $backend->payloads);
        self::assertSame('Ada', $storage->get('name'));

        $storage->regenerate(true);
        $currentId = $this->lastCookieValue($emissions);

        self::assertNotSame($retainedOldId, $currentId);
        self::assertArrayNotHasKey('test:' . $retainedOldId, $backend->payloads);
        self::assertArrayHasKey('test:' . $originalId, $backend->payloads);
        self::assertSame('Ada', $storage->get('name'));
    }

    public function testDestroyFailsWhenRedisCannotConfirmInvalidation(): void
    {
        $backend = new SessionRedisBackendDouble();
        $emissions = [];
        $storage = $this->storage($backend, $emissions);
        $storage->start();
        $backend->failNextDelete = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to invalidate Redis session key');

        $storage->destroy();
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     */
    private function storage(SessionRedisBackendDouble $backend, array &$emissions): RedisSessionStorage
    {
        return new RedisSessionStorage(
            prefix: 'test:',
            lifetimeSeconds: 60,
            cookieName: 'TEST_SESSION',
            cookie: $this->cookie($emissions),
            clientFactory: static fn(): RedisSessionClientInterface => new SessionRedisClientDouble($backend),
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
            secure: SessionCookieSecure::Always,
        );
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
}

final class SessionRedisBackendDouble
{
    /**
     * @var array<string, string>
     */
    public array $payloads = [];

    /**
     * @var array<string, int>
     */
    public array $expiresAt = [];

    public bool $removeOnNextExpire = false;
    public bool $failNextDelete = false;
}

final readonly class SessionRedisClientDouble implements RedisSessionClientInterface
{
    public function __construct(private SessionRedisBackendDouble $backend) {}

    public function connect(string $host, int $port, float $timeout): bool
    {
        return true;
    }

    public function authenticate(string $password): bool
    {
        return true;
    }

    public function selectDatabase(int $database): bool
    {
        return true;
    }

    public function get(string $key): ?string
    {
        $this->purgeExpired($key);

        return $this->backend->payloads[$key] ?? null;
    }

    public function exists(string $key): bool
    {
        $this->purgeExpired($key);

        return isset($this->backend->payloads[$key]);
    }

    public function expire(string $key, int $seconds): bool
    {
        $this->purgeExpired($key);

        if ($this->backend->removeOnNextExpire) {
            $this->backend->removeOnNextExpire = false;
            unset($this->backend->payloads[$key], $this->backend->expiresAt[$key]);

            return false;
        }
        if (!isset($this->backend->payloads[$key])) {
            return false;
        }

        $this->backend->expiresAt[$key] = time() + $seconds;

        return true;
    }

    public function setex(string $key, int $seconds, string $payload): bool
    {
        $this->backend->payloads[$key] = $payload;
        $this->backend->expiresAt[$key] = time() + $seconds;

        return true;
    }

    public function delete(string $key): bool
    {
        if ($this->backend->failNextDelete) {
            $this->backend->failNextDelete = false;

            return false;
        }

        unset($this->backend->payloads[$key], $this->backend->expiresAt[$key]);

        return true;
    }

    public function close(): void {}

    private function purgeExpired(string $key): void
    {
        if (($this->backend->expiresAt[$key] ?? PHP_INT_MAX) >= time()) {
            return;
        }

        unset($this->backend->payloads[$key], $this->backend->expiresAt[$key]);
    }
}
