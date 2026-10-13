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

        $first = $limiter->hit('opaque-key', $policy);
        $second = $limiter->hit('opaque-key', $policy);
        $status = $limiter->status('opaque-key', $policy);

        self::assertTrue($first->allowed());
        self::assertSame(1, $first->remaining());
        self::assertFalse($second->allowed());
        self::assertFalse($status->allowed());
        self::assertSame(hash_hmac('sha256', 'opaque-key', 'application-secret', true), $connection->lastKeyHash);
        self::assertNotSame('opaque-key', $connection->lastKeyHash);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $connection->lastStatement);
    }

    public function testClearRemovesTheBucket(): void
    {
        $connection = new RateLimitConnection();
        $limiter = new DatabaseRateLimiter(
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
            'application-secret',
        );
        $policy = new RateLimitPolicy(1, 900);

        $limiter->hit('opaque-key', $policy);
        $limiter->clear('opaque-key');

        self::assertTrue($limiter->status('opaque-key', $policy)->allowed());
        self::assertSame(1, $limiter->status('opaque-key', $policy)->remaining());
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

    /** @var array<string, array{attempts: int, expires_at: string}> */
    private array $rows = [];

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
        $keyHash = $bindings[0] ?? '';
        if (!is_string($keyHash)) {
            return 0;
        }

        $this->lastKeyHash = $keyHash;

        if (str_starts_with($sql, 'DELETE')) {
            unset($this->rows[$keyHash]);

            return 1;
        }

        $windowSeconds = $bindings[1] ?? 0;
        if (!is_int($windowSeconds)) {
            return 0;
        }

        $row = $this->rows[$keyHash] ?? null;
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $windowSeconds);
        $this->rows[$keyHash] = [
            'attempts' => $row === null ? 1 : $row['attempts'] + 1,
            'expires_at' => $expiresAt,
        ];

        return 1;
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
