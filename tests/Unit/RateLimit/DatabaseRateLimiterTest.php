<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\RateLimit;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\RateLimit\DatabaseRateLimiter;
use Lemonade\Framework\RateLimit\RateLimitPolicy;
use PHPUnit\Framework\TestCase;

final class DatabaseRateLimiterTest extends TestCase
{
    public function testHitUsesABinaryHmacAndAtomicallyBlocksAtTheConfiguredLimit(): void
    {
        $connection = new RateLimitConnection();
        $limiter = new DatabaseRateLimiter(
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
            'application-secret',
        );
        $policy = new RateLimitPolicy(2, 900);

        $first = $limiter->hit('opaque-key', 'test.fixed-window', $policy);
        $second = $limiter->hit('opaque-key', 'test.fixed-window', $policy);
        $status = $limiter->status('opaque-key', 'test.fixed-window', $policy);

        self::assertTrue($first->allowed());
        self::assertSame(1, $first->remaining());
        self::assertFalse($second->allowed());
        self::assertFalse($status->allowed());
        self::assertSame(hash_hmac('sha256', 'opaque-key', 'application-secret', true), $connection->lastKeyHash);
        self::assertNotSame('opaque-key', $connection->lastKeyHash);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $connection->lastStatement);
    }

    public function testHitStoresScopeWithoutChangingTheHmacBucketIdentity(): void
    {
        $connection = new RateLimitConnection();
        $limiter = new DatabaseRateLimiter(
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
            'application-secret',
        );
        $policy = new RateLimitPolicy(2, 900);

        $limiter->hit('opaque-key', 'test.initial', $policy);
        $limiter->hit('opaque-key', 'test.reclassified', $policy);

        self::assertSame(hash_hmac('sha256', 'opaque-key', 'application-secret', true), $connection->lastKeyHash);
        self::assertSame('test.reclassified', $connection->scopeFor('opaque-key', 'application-secret'));
        self::assertFalse($limiter->status('opaque-key', 'test.reclassified', $policy)->allowed());
    }

    public function testStatusDoesNotMutateScopeMetadata(): void
    {
        $connection = new RateLimitConnection();
        $limiter = new DatabaseRateLimiter(
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
            'application-secret',
        );
        $policy = new RateLimitPolicy(3, 900);

        $limiter->hit('opaque-key', 'generic.action', $policy);
        $statementCount = $connection->statementCount;
        $limiter->status('opaque-key', 'other.scope', $policy);

        self::assertSame('generic.action', $connection->scopeFor('opaque-key', 'application-secret'));
        self::assertSame($statementCount, $connection->statementCount);
    }

    public function testInvalidScopeIsRejected(): void
    {
        $limiter = new DatabaseRateLimiter(
            new Database(new RateLimitConnection(), $this->createMock(DatabaseDriverInterface::class)),
            'application-secret',
        );

        $this->expectException(\InvalidArgumentException::class);
        $limiter->hit('opaque-key', 'admin auth/login', new RateLimitPolicy(1, 900));
    }

    public function testClearRemovesTheBucket(): void
    {
        $connection = new RateLimitConnection();
        $limiter = new DatabaseRateLimiter(
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
            'application-secret',
        );
        $policy = new RateLimitPolicy(1, 900);

        $limiter->hit('opaque-key', 'test.clear', $policy);
        $limiter->clear('opaque-key');

        self::assertTrue($limiter->status('opaque-key', 'test.clear', $policy)->allowed());
        self::assertSame(1, $limiter->status('opaque-key', 'test.clear', $policy)->remaining());
    }

    public function testEmptyApplicationSecretIsRejected(): void
    {
        $this->expectException(\LogicException::class);

        new DatabaseRateLimiter(
            new Database(new RateLimitConnection(), $this->createMock(DatabaseDriverInterface::class)),
            '',
        );
    }
}

final class RateLimitConnection implements ConnectionInterface
{
    public string $lastKeyHash = '';
    public string $lastStatement = '';

    /** @var array<string, array{attempts: int, expires_at: string, scope: string}> */
    private array $rows = [];

    public int $statementCount = 0;

    public function select(string $sql, array $bindings = []): array
    {
        unset($sql);

        $keyHash = $bindings[0] ?? '';
        if (!is_string($keyHash) || !isset($this->rows[$keyHash])) {
            return [];
        }

        return [$this->rows[$keyHash]];
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        foreach ($this->select($sql, $bindings) as $row) {
            yield $row;
        }
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $this->lastStatement = $sql;
        $this->statementCount++;
        $keyHash = $bindings[0] ?? '';
        if (!is_string($keyHash)) {
            return 0;
        }

        $this->lastKeyHash = $keyHash;

        if (str_starts_with($sql, 'DELETE')) {
            unset($this->rows[$keyHash]);

            return 1;
        }

        $scope = $bindings[1] ?? null;
        if (!is_string($scope)) {
            return 0;
        }

        $windowSeconds = $bindings[2] ?? 0;
        if (!is_int($windowSeconds)) {
            return 0;
        }

        $row = $this->rows[$keyHash] ?? null;
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $windowSeconds);
        $this->rows[$keyHash] = [
            'attempts' => $row === null ? 1 : $row['attempts'] + 1,
            'expires_at' => $expiresAt,
            'scope' => $scope,
        ];

        return 1;
    }

    public function scopeFor(string $key, string $secret): ?string
    {
        return $this->rows[hash_hmac('sha256', $key, $secret, true)]['scope'] ?? null;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
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
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}
