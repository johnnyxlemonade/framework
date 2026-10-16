<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use Lemonade\Framework\Session\Storage\NativeSessionStorage;
use Lemonade\Framework\Session\Storage\SessionCookie;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class NativeSessionStorageTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $_COOKIE = [];
        $_SERVER = [];
        $this->directory = sys_get_temp_dir() . '/lemonade-native-session-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->destroyNativeSession();
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

    public function testItIsNotStartedWhenNoNativeSessionIsActive(): void
    {
        self::assertSame(PHP_SESSION_NONE, session_status());

        $storage = new NativeSessionStorage(cookieName: 'FRAMEWORK_SESSION');

        self::assertFalse($storage->started());
    }

    public function testItIsStartedWhenActiveNativeSessionUsesItsCookieName(): void
    {
        $this->startNativeSession('FRAMEWORK_SESSION');

        try {
            $storage = new NativeSessionStorage(cookieName: 'FRAMEWORK_SESSION');

            self::assertTrue($storage->started());
        } finally {
            $this->destroyNativeSession();
        }
    }

    public function testItIsNotStartedWhenAnotherNativeSessionIsActive(): void
    {
        $this->startNativeSession('FOREIGN_SESSION');

        try {
            $storage = new NativeSessionStorage(cookieName: 'FRAMEWORK_SESSION');

            self::assertFalse($storage->started());
        } finally {
            $this->destroyNativeSession();
        }
    }

    public function testStartFailsExplicitlyWhenAnotherNativeSessionIsActive(): void
    {
        $this->startNativeSession('FOREIGN_SESSION');

        try {
            $storage = new NativeSessionStorage(cookieName: 'FRAMEWORK_SESSION');

            try {
                $storage->start();
                self::fail('Starting over a foreign native session should fail.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'Cannot start native PHP session "FRAMEWORK_SESSION" because session "FOREIGN_SESSION" is already active.',
                    $exception->getMessage(),
                );
                self::assertSame('FOREIGN_SESSION', session_name());
                self::assertSame(PHP_SESSION_ACTIVE, session_status());
            }
        } finally {
            $this->destroyNativeSession();
        }
    }

    public function testStartOwnsStrictModeGarbageCollectionAndSlidingCookiePolicy(): void
    {
        $emissions = [];
        $storage = $this->storage(
            $emissions,
            new SessionGarbageCollectionPolicy(2, 101),
        );
        $startedAt = time();

        $storage->start();

        self::assertSame('60', ini_get('session.gc_maxlifetime'));
        self::assertSame('2', ini_get('session.gc_probability'));
        self::assertSame('101', ini_get('session.gc_divisor'));
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.use_cookies'));
        self::assertSame('1', ini_get('session.use_only_cookies'));
        self::assertSame(session_id(), $this->lastCookieValue($emissions));
        self::assertGreaterThanOrEqual($startedAt + 60, $this->lastCookieOptions($emissions)['expires']);
        self::assertSame(0700, fileperms($this->directory) & 0777);
    }

    public function testStrictModeRejectsAStaleCookieIdentity(): void
    {
        $staleId = str_repeat('a', 32);
        $_COOKIE['TEST_NATIVE_SESSION'] = $staleId;
        $emissions = [];

        $this->storage($emissions)->start();

        self::assertNotSame($staleId, session_id());
        self::assertSame(session_id(), $this->lastCookieValue($emissions));
    }

    public function testNativeRuntimeReceivesTheConfiguredSameSiteValue(): void
    {
        $emissions = [];
        $storage = $this->storage(
            emissions: $emissions,
            cookieSameSite: SessionCookieSameSite::Strict,
        );

        $storage->start();

        self::assertSame(
            SessionCookieSameSite::Strict->value,
            session_get_cookie_params()['samesite'],
        );
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
        $sessionId = $this->currentSessionId();

        $storage->clear();

        self::assertSame($sessionId, session_id());
        self::assertFalse($storage->has('name'));

        $_COOKIE['TEST_NATIVE_SESSION'] = $sessionId;
        $storage->destroy();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame('', $this->lastCookieValue($emissions));
        self::assertLessThan(time(), $this->lastCookieOptions($emissions)['expires']);

        $storage->start();

        self::assertNotSame($sessionId, session_id());
    }

    public function testRegeneratePreservesPayloadAndHonorsOldRecordPolicy(): void
    {
        $emissions = [];
        $storage = $this->storage($emissions);
        $storage->set('name', 'Ada');
        $originalId = $this->currentSessionId();
        session_write_close();
        self::assertFileExists($this->nativeFile($originalId));

        $_COOKIE['TEST_NATIVE_SESSION'] = $originalId;
        $storage->start();
        $storage->regenerate(false);
        $retainedOldId = $this->currentSessionId();

        self::assertNotSame($originalId, $retainedOldId);
        self::assertFileExists($this->nativeFile($originalId));
        self::assertSame('Ada', $storage->get('name'));
        session_write_close();

        $_COOKIE['TEST_NATIVE_SESSION'] = $retainedOldId;
        $storage->start();
        $storage->regenerate(true);

        self::assertNotSame($retainedOldId, session_id());
        self::assertFileDoesNotExist($this->nativeFile($retainedOldId));
        self::assertFileExists($this->nativeFile($originalId));
        self::assertSame('Ada', $storage->get('name'));
    }

    private function startNativeSession(string $name): void
    {
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        session_name($name);
        session_id(bin2hex(random_bytes(16)));

        if (session_start() === false) {
            throw new RuntimeException('Failed to start isolated native session fixture.');
        }
    }

    private function destroyNativeSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_destroy();
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     */
    private function storage(
        array &$emissions,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
        SessionCookieSameSite $cookieSameSite = SessionCookieSameSite::Lax,
    ): NativeSessionStorage {
        return new NativeSessionStorage(
            cookieName: 'TEST_NATIVE_SESSION',
            lifetimeSeconds: 60,
            savePath: $this->directory,
            cookie: new SessionCookie(
                'TEST_NATIVE_SESSION',
                60,
                $cookieSameSite,
                static function (string $name, string $value, array $options) use (&$emissions): bool {
                    $emissions[] = [
                        'name' => $name,
                        'value' => $value,
                        'options' => $options,
                    ];

                    return true;
                },
            ),
            garbageCollectionPolicy: $garbageCollectionPolicy,
        );
    }

    private function nativeFile(string $sessionId): string
    {
        return $this->directory . '/sess_' . $sessionId;
    }

    private function currentSessionId(): string
    {
        $sessionId = session_id();

        if (!is_string($sessionId) || $sessionId === '') {
            self::fail('Expected an active native session identifier.');
        }

        return $sessionId;
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
