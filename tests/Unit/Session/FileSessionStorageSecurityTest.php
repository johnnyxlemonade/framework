<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Cookie\SessionCookieSecure;
use Lemonade\Framework\Session\Exception\SessionStorageException;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use Lemonade\Framework\Session\Storage\FileSessionStorage;
use Lemonade\Framework\Session\Storage\SessionCookie;
use PHPUnit\Framework\TestCase;

final class FileSessionStorageSecurityTest extends TestCase
{
    private string $workspace = '';
    private string $directory = '';
    private string $publicRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        $_COOKIE = [];
        $_SERVER = [];
        $this->workspace = sys_get_temp_dir() . '/lemonade-file-session-security-' . bin2hex(random_bytes(8));
        $this->directory = $this->workspace . '/private/sessions';
        $this->publicRoot = $this->workspace . '/public';
        mkdir($this->publicRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_SERVER = [];
        $this->removeTree($this->workspace);

        parent::tearDown();
    }

    public function testRelativeStorageRootIsRejectedAtConstruction(): void
    {
        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('must be an absolute local path without traversal');

        new FileSessionStorage('relative/sessions', $this->publicRoot);
    }

    public function testTraversalStorageRootIsRejectedAtConstruction(): void
    {
        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('must be an absolute local path without traversal');

        new FileSessionStorage($this->workspace . '/private/../public/sessions', $this->publicRoot);
    }

    public function testStorageInsidePublicWebrootIsRejected(): void
    {
        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('must be separated from the public web root');

        new FileSessionStorage($this->publicRoot . '/sessions', $this->publicRoot);
    }

    public function testStorageThatContainsPublicWebrootIsRejected(): void
    {
        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('must be separated from the public web root');

        new FileSessionStorage($this->workspace, $this->publicRoot);
    }

    public function testSymbolicLinkPathComponentIsRejected(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $outside = $this->workspace . '/outside';
        $link = $this->workspace . '/private';
        mkdir($outside, 0700, true);

        if (!@symlink($outside, $link)) {
            self::markTestSkipped('Symbolic links cannot be created in this environment.');
        }

        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('cannot contain symbolic links');

        new FileSessionStorage($link . '/sessions', $this->publicRoot);
    }

    public function testCanonicalPublicWebrootOverlapThroughAliasIsRejected(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $served = $this->workspace . '/served';
        $publicAlias = $this->workspace . '/public-alias';
        mkdir($served, 0700, true);

        if (!@symlink($served, $publicAlias)) {
            self::markTestSkipped('Symbolic links cannot be created in this environment.');
        }

        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('Canonical file session storage overlaps the public web root');

        new FileSessionStorage($served . '/sessions', $publicAlias);
    }

    public function testExistingDirectoryFilesAndControlFilesAreRestrictedAndOwned(): void
    {
        $sessionId = str_repeat('a', 64);
        mkdir($this->directory, 0777, true);
        chmod($this->directory, 0777);
        file_put_contents($this->sessionFile($sessionId), serialize(['name' => 'Ada']));
        chmod($this->sessionFile($sessionId), 0666);
        $_COOKIE['SECURE_SESSION'] = $sessionId;
        $emissions = [];

        $storage = $this->storage(
            emissions: $emissions,
            garbageCollectionPolicy: new SessionGarbageCollectionPolicy(1, 1),
        );
        $storage->start();

        $lockFile = $this->directory . '/.session-files.lock';
        $cursorFile = $this->directory . '/.session-gc.cursor';
        self::assertSame('Ada', $storage->get('name'));
        self::assertSame(0700, fileperms($this->directory) & 0777);
        self::assertSame(0600, fileperms($this->sessionFile($sessionId)) & 0777);
        self::assertSame(0600, fileperms($lockFile) & 0777);
        self::assertSame(0600, fileperms($cursorFile) & 0777);
        self::assertSame(fileowner($this->directory), fileowner($this->sessionFile($sessionId)));
        self::assertSame(fileowner($this->directory), fileowner($lockFile));
        self::assertSame(fileowner($this->directory), fileowner($cursorFile));
    }

    public function testOwnershipGuardRejectsForeignFileMetadata(): void
    {
        mkdir($this->directory, 0700, true);
        $file = $this->directory . '/ownership-fixture';
        file_put_contents($file, 'fixture');
        $stat = lstat($file);
        self::assertIsArray($stat);
        $stat['uid'] = (int) $stat['uid'] + 1;
        $emissions = [];
        $storage = $this->storage($emissions);
        $ownershipGuard = (new \ReflectionObject($storage))->getMethod('ownedRegularFile');

        self::assertFalse($ownershipGuard->invoke($storage, $stat));
    }

    public function testSessionSymlinkCannotReadOrOverwriteOutsideFile(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $sessionId = str_repeat('c', 64);
        $outside = $this->workspace . '/outside-session';
        $outsideContents = serialize(['secret' => 'outside']);
        mkdir($this->directory, 0700, true);
        file_put_contents($outside, $outsideContents);
        chmod($outside, 0600);

        if (!@symlink($outside, $this->sessionFile($sessionId))) {
            self::markTestSkipped('Symbolic links cannot be created in this environment.');
        }

        $_COOKIE['SECURE_SESSION'] = $sessionId;
        $emissions = [];
        $storage = $this->storage($emissions);

        try {
            $storage->start();
            self::fail('Expected a symbolic session file to be rejected.');
        } catch (SessionStorageException) {
            self::assertSame($outsideContents, file_get_contents($outside));
            self::assertTrue(is_link($this->sessionFile($sessionId)));
            self::assertSame([], $emissions);
        }
    }

    public function testGarbageCollectionCursorSymlinkCannotOverwriteOutsideFile(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $outside = $this->workspace . '/outside-cursor';
        $outsideContents = 'do-not-change';
        mkdir($this->directory, 0700, true);
        file_put_contents($outside, $outsideContents);
        chmod($outside, 0600);

        if (!@symlink($outside, $this->directory . '/.session-gc.cursor')) {
            self::markTestSkipped('Symbolic links cannot be created in this environment.');
        }

        $emissions = [];
        $storage = $this->storage(
            emissions: $emissions,
            garbageCollectionPolicy: new SessionGarbageCollectionPolicy(1, 1),
        );

        try {
            $storage->start();
            self::fail('Expected a symbolic GC cursor to be rejected.');
        } catch (SessionStorageException) {
            self::assertSame($outsideContents, file_get_contents($outside));
            self::assertTrue(is_link($this->directory . '/.session-gc.cursor'));
        }
    }

    public function testHardLinkedSessionFileIsRejectedWithoutChangingSource(): void
    {
        if (!function_exists('link')) {
            self::markTestSkipped('Hard links are unavailable.');
        }

        $sessionId = str_repeat('d', 64);
        $outside = $this->workspace . '/outside-hardlink';
        $outsideContents = serialize(['secret' => 'outside']);
        mkdir($this->directory, 0700, true);
        file_put_contents($outside, $outsideContents);
        chmod($outside, 0600);

        if (!@link($outside, $this->sessionFile($sessionId))) {
            self::markTestSkipped('Hard links cannot be created in this environment.');
        }

        $_COOKIE['SECURE_SESSION'] = $sessionId;
        $emissions = [];
        $storage = $this->storage($emissions);

        try {
            $storage->start();
            self::fail('Expected a hard-linked session file to be rejected.');
        } catch (SessionStorageException) {
            self::assertSame($outsideContents, file_get_contents($outside));
            $outsideStat = stat($outside);

            if (!is_array($outsideStat)) {
                self::fail('Expected hard-link metadata to remain readable.');
            }

            self::assertSame(2, $outsideStat['nlink']);
        }
    }

    public function testAtomicReplacementKeepsConcurrentReaderSnapshotComplete(): void
    {
        $firstEmissions = [];
        $first = $this->storage($firstEmissions);
        $first->set('value', 'before');
        $sessionId = $this->lastCookieValue($firstEmissions);
        $_COOKIE['SECURE_SESSION'] = $sessionId;
        $secondEmissions = [];
        $second = $this->storage($secondEmissions);
        $second->start();

        $reader = fopen($this->sessionFile($sessionId), 'rb');
        self::assertIsResource($reader);
        self::assertTrue(flock($reader, LOCK_SH));

        $second->set('value', 'after');
        $oldSnapshot = stream_get_contents($reader);
        flock($reader, LOCK_UN);
        fclose($reader);
        $newSnapshot = file_get_contents($this->sessionFile($sessionId));

        self::assertIsString($oldSnapshot);
        self::assertIsString($newSnapshot);
        self::assertSame(['value' => 'before'], unserialize($oldSnapshot));
        self::assertSame(['value' => 'after'], unserialize($newSnapshot));
    }

    public function testActiveStorageRejectsReplacementOfManagedRoot(): void
    {
        $emissions = [];
        $storage = $this->storage($emissions);
        $storage->set('value', 'before');
        $retired = $this->workspace . '/retired-sessions';
        rename($this->directory, $retired);
        mkdir($this->directory, 0700, true);

        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('root identity changed');

        $storage->set('value', 'after');
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     */
    private function storage(
        array &$emissions,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
    ): FileSessionStorage {
        return new FileSessionStorage(
            directory: $this->directory,
            publicRoot: $this->publicRoot,
            lifetimeSeconds: 60,
            cookieName: 'SECURE_SESSION',
            cookie: new SessionCookie(
                name: 'SECURE_SESSION',
                lifetimeSeconds: 60,
                sameSite: SessionCookieSameSite::Lax,
                emitter: static function (string $name, string $value, array $options) use (&$emissions): bool {
                    $emissions[] = [
                        'name' => $name,
                        'value' => $value,
                        'options' => $options,
                    ];

                    return true;
                },
                secure: SessionCookieSecure::Always,
            ),
            garbageCollectionPolicy: $garbageCollectionPolicy ?? new SessionGarbageCollectionPolicy(0, 1),
        );
    }

    private function sessionFile(string $sessionId): string
    {
        return $this->directory . '/sess_' . $sessionId;
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

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
                }
            }
        }

        @rmdir($path);
    }
}
