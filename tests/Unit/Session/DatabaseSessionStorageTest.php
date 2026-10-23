<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Generator;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Cookie\SessionCookieSecure;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use Lemonade\Framework\Session\Storage\DatabaseSessionStorage;
use Lemonade\Framework\Session\Storage\SessionCookie;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseSessionStorageTest extends TestCase
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
        $connection = new SessionDatabaseConnectionDouble();
        $missingId = str_repeat('a', 64);
        $_COOKIE['TEST_SESSION'] = $missingId;
        $emissions = [];

        $this->storage($connection, $emissions)->start();

        $newId = $this->lastCookieValue($emissions);
        self::assertNotSame($missingId, $newId);
        self::assertArrayHasKey($newId, $connection->rows);

        $expiredId = str_repeat('b', 64);
        $connection->rows[$expiredId] = $this->row(['name' => 'expired'], time() - 1);
        $_COOKIE['TEST_SESSION'] = $expiredId;
        $emissions = [];

        $this->storage($connection, $emissions)->start();

        self::assertNotSame($expiredId, $this->lastCookieValue($emissions));
        self::assertArrayNotHasKey($expiredId, $connection->rows);
    }

    public function testInvalidCookieIdentityIsRejectedEvenWhenItsRowExists(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $invalidId = str_repeat('A', 64);
        $connection->rows[$invalidId] = $this->row(['name' => 'Ada'], time() + 60);
        $_COOKIE['TEST_SESSION'] = $invalidId;
        $emissions = [];
        $storage = $this->storage($connection, $emissions);

        $storage->start();

        self::assertNotSame($invalidId, $this->lastCookieValue($emissions));
        self::assertNull($storage->get('name'));
    }

    public function testReadOnlyStartRefreshesRowAndPersistentCookieRetention(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $sessionId = str_repeat('c', 64);
        $connection->rows[$sessionId] = $this->row(['name' => 'Ada'], time() + 10);
        $_COOKIE['TEST_SESSION'] = $sessionId;
        $emissions = [];
        $startedAt = time();
        $storage = $this->storage($connection, $emissions);

        $storage->start();

        self::assertSame('Ada', $storage->get('name'));
        self::assertGreaterThanOrEqual($startedAt + 60, $connection->rows[$sessionId]['expires_at']);
        self::assertSame($sessionId, $this->lastCookieValue($emissions));
        self::assertGreaterThanOrEqual($startedAt + 60, $this->lastCookieOptions($emissions)['expires']);
    }

    public function testStoredNullRemainsDistinctFromAMissingKey(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $emissions = [];
        $storage = $this->storage($connection, $emissions);

        $storage->set('nullable', null);

        self::assertTrue($storage->has('nullable'));
        self::assertNull($storage->get('nullable', 'fallback'));
        self::assertSame('fallback', $storage->get('missing', 'fallback'));
    }

    public function testClearKeepsIdentityWhileDestroyRemovesItAndAllowsRestart(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $emissions = [];
        $storage = $this->storage($connection, $emissions);
        $storage->set('name', 'Ada');
        $sessionId = $this->lastCookieValue($emissions);

        $storage->clear();

        self::assertArrayHasKey($sessionId, $connection->rows);
        self::assertSame([], unserialize($connection->rows[$sessionId]['payload']));

        $storage->destroy();

        self::assertArrayNotHasKey($sessionId, $connection->rows);
        self::assertSame('', $this->lastCookieValue($emissions));
        self::assertFalse($storage->started());

        $storage->start();

        self::assertNotSame($sessionId, $this->lastCookieValue($emissions));
    }

    public function testRegeneratePreservesPayloadAndHonorsOldRecordPolicy(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $emissions = [];
        $storage = $this->storage($connection, $emissions);
        $storage->set('name', 'Ada');
        $originalId = $this->lastCookieValue($emissions);

        $storage->regenerate(false);
        $retainedOldId = $this->lastCookieValue($emissions);

        self::assertArrayHasKey($originalId, $connection->rows);
        self::assertSame('Ada', $storage->get('name'));

        $storage->regenerate(true);
        $currentId = $this->lastCookieValue($emissions);

        self::assertNotSame($retainedOldId, $currentId);
        self::assertArrayNotHasKey($retainedOldId, $connection->rows);
        self::assertArrayHasKey($originalId, $connection->rows);
        self::assertSame('Ada', $storage->get('name'));
    }

    public function testOpportunisticGarbageCollectionDeletesOnlyOneBoundedBatch(): void
    {
        $connection = new SessionDatabaseConnectionDouble();

        for ($index = 0; $index < 5; ++$index) {
            $sessionId = hash('sha256', 'expired-' . $index);
            $connection->rows[$sessionId] = $this->row(['index' => $index], time() - 10 - $index);
        }

        $emissions = [];
        $this->storage(
            connection: $connection,
            emissions: $emissions,
            garbageCollectionPolicy: new SessionGarbageCollectionPolicy(1, 1),
            gcBatchSize: 2,
        )->start();

        $expiredRows = array_filter(
            $connection->rows,
            static fn(array $row): bool => $row['expires_at'] < time(),
        );
        self::assertCount(3, $expiredRows);
        self::assertSame(2, $connection->cleanupDeleteBatchSizes[0] ?? null);
        self::assertFalse($connection->usedUnboundedCleanup);
    }

    public function testGarbageCollectionDeletesExpiredNonCanonicalIdentifiers(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $connection->rows['legacy-session-id'] = $this->row(['legacy' => true], time() - 10);
        $emissions = [];

        $this->storage(
            connection: $connection,
            emissions: $emissions,
            garbageCollectionPolicy: new SessionGarbageCollectionPolicy(1, 1),
            gcBatchSize: 10,
        )->start();

        self::assertArrayNotHasKey('legacy-session-id', $connection->rows);
        self::assertSame(1, $connection->cleanupDeleteBatchSizes[0] ?? null);
    }

    public function testConcurrentInsertWinnerIsReusedWithoutFailingStart(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $connection->simulateInsertRace = true;
        $emissions = [];

        $this->storage($connection, $emissions)->start();

        self::assertCount(1, $connection->rows);
        self::assertArrayHasKey($this->lastCookieValue($emissions), $connection->rows);
    }

    public function testRecordRemovedBetweenReadAndTouchGetsFreshIdentity(): void
    {
        $connection = new SessionDatabaseConnectionDouble();
        $oldId = str_repeat('d', 64);
        $connection->rows[$oldId] = $this->row(['name' => 'Ada'], time() + 60);
        $connection->removeBeforeNextTouch = $oldId;
        $_COOKIE['TEST_SESSION'] = $oldId;
        $emissions = [];

        $this->storage($connection, $emissions)->start();

        self::assertNotSame($oldId, $this->lastCookieValue($emissions));
        self::assertArrayNotHasKey($oldId, $connection->rows);
    }

    /**
     * @param list<array{name: string, value: string, options: array<string, int|string|bool>}> $emissions
     */
    private function storage(
        SessionDatabaseConnectionDouble $connection,
        array &$emissions,
        ?SessionGarbageCollectionPolicy $garbageCollectionPolicy = null,
        int $gcBatchSize = 100,
    ): DatabaseSessionStorage {
        return new DatabaseSessionStorage(
            connection: $connection,
            table: 'sessions',
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
            secure: SessionCookieSecure::Always,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{payload: string, updated_at: int, expires_at: int}
     */
    private function row(array $payload, int $expiresAt): array
    {
        return [
            'payload' => serialize($payload),
            'updated_at' => time() - 10,
            'expires_at' => $expiresAt,
        ];
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

/**
 * Minimal in-memory SQL contract double for database session lifecycle tests.
 */
final class SessionDatabaseConnectionDouble implements ConnectionInterface
{
    /**
     * @var array<string, array{payload: string, updated_at: int, expires_at: int}>
     */
    public array $rows = [];

    public bool $simulateInsertRace = false;
    public ?string $removeBeforeNextTouch = null;

    /**
     * @var list<int>
     */
    public array $cleanupDeleteBatchSizes = [];

    public bool $usedUnboundedCleanup = false;
    private int $affectedRows = 0;
    private bool $transactionActive = false;

    public function select(string $sql, array $bindings = []): array
    {
        if (str_starts_with($sql, 'SELECT payload, expires_at')) {
            $sessionId = (string) $bindings[0];

            return isset($this->rows[$sessionId])
                ? [[
                    'payload' => $this->rows[$sessionId]['payload'],
                    'expires_at' => $this->rows[$sessionId]['expires_at'],
                ]]
                : [];
        }

        if (str_contains($sql, 'WHERE session_id = ? LIMIT 1')) {
            $sessionId = (string) $bindings[0];

            return isset($this->rows[$sessionId]) ? [['session_id' => $sessionId]] : [];
        }

        if (str_contains($sql, 'WHERE expires_at < ?')) {
            $expiresAt = (int) $bindings[0];
            $rows = [];

            foreach ($this->rows as $sessionId => $row) {
                if ($row['expires_at'] < $expiresAt) {
                    $rows[] = [
                        'session_id' => $sessionId,
                        'expires_at' => $row['expires_at'],
                    ];
                }
            }

            usort(
                $rows,
                static fn(array $left, array $right): int => $left['expires_at'] <=> $right['expires_at'],
            );
            preg_match('/LIMIT ([0-9]+)$/D', $sql, $matches);

            return array_map(
                static fn(array $row): array => ['session_id' => $row['session_id']],
                array_slice($rows, 0, (int) ($matches[1] ?? 0)),
            );
        }

        throw new RuntimeException('Unexpected select SQL: ' . $sql);
    }

    public function cursor(string $sql, array $bindings = []): Generator
    {
        foreach ($this->select($sql, $bindings) as $row) {
            yield $row;
        }
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $this->affectedRows = 0;

        if (str_starts_with($sql, 'UPDATE ')) {
            $sessionId = (string) $bindings[array_key_last($bindings)];

            if ($this->removeBeforeNextTouch === $sessionId && !str_contains($sql, 'payload = ?')) {
                unset($this->rows[$sessionId]);
                $this->removeBeforeNextTouch = null;
            }
            if (!isset($this->rows[$sessionId])) {
                return 0;
            }

            if (str_contains($sql, 'payload = ?')) {
                $this->rows[$sessionId] = [
                    'payload' => (string) $bindings[0],
                    'updated_at' => (int) $bindings[1],
                    'expires_at' => (int) $bindings[2],
                ];
            } else {
                $this->rows[$sessionId]['updated_at'] = (int) $bindings[0];
                $this->rows[$sessionId]['expires_at'] = (int) $bindings[1];
            }

            return $this->affectedRows = 1;
        }

        if (str_starts_with($sql, 'INSERT INTO ')) {
            $sessionId = (string) $bindings[0];
            $row = [
                'payload' => (string) $bindings[1],
                'updated_at' => (int) $bindings[2],
                'expires_at' => (int) $bindings[3],
            ];

            if ($this->simulateInsertRace) {
                $this->simulateInsertRace = false;
                $this->rows[$sessionId] = $row;

                throw new RuntimeException('Simulated unique-key race.');
            }
            if (isset($this->rows[$sessionId])) {
                throw new RuntimeException('Duplicate session identifier.');
            }

            $this->rows[$sessionId] = $row;

            return $this->affectedRows = 1;
        }

        if (str_contains($sql, 'WHERE session_id IN (')) {
            $expiresAt = (int) array_pop($bindings);
            $this->cleanupDeleteBatchSizes[] = count($bindings);

            foreach ($bindings as $sessionId) {
                if (isset($this->rows[$sessionId]) && $this->rows[$sessionId]['expires_at'] < $expiresAt) {
                    unset($this->rows[$sessionId]);
                    ++$this->affectedRows;
                }
            }

            return $this->affectedRows;
        }

        if (str_starts_with($sql, 'DELETE FROM ') && str_contains($sql, 'WHERE expires_at < ?')) {
            $this->usedUnboundedCleanup = true;

            return 0;
        }

        if (str_starts_with($sql, 'DELETE FROM ')) {
            $sessionId = (string) $bindings[0];

            if (isset($this->rows[$sessionId])) {
                unset($this->rows[$sessionId]);

                return $this->affectedRows = 1;
            }

            return 0;
        }

        throw new RuntimeException('Unexpected statement SQL: ' . $sql);
    }

    public function beginTransaction(): void
    {
        $this->transactionActive = true;
    }

    public function commit(): void
    {
        $this->transactionActive = false;
    }

    public function rollBack(): void
    {
        $this->transactionActive = false;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return addslashes($value);
    }
}
