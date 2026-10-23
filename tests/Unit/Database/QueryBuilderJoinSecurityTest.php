<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteIdentifierEscaper;
use Lemonade\Framework\Database\QueryBuilder;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryBuilderJoinSecurityTest extends TestCase
{
    public function testLegacyJoinKeepsLegitimateRawConditionAndValidatesTableAlias(): void
    {
        $driver = new QueryBuilderJoinDriverSpy();

        QueryBuilder::make($driver)
            ->table('users AS u')
            ->select(['u.id', 'p.display_name'])
            ->join('profiles p', 'p.user_id = u.id', 'left')
            ->where('p.enabled', true)
            ->get();

        self::assertSame(
            'SELECT "u"."id", "p"."display_name" FROM "users" AS "u" '
            . 'LEFT JOIN "profiles" AS "p" ON p.user_id = u.id WHERE "p"."enabled" = ?',
            $driver->lastSql(),
        );
        self::assertSame([true], $driver->lastBindings());
    }

    /**
     * @param non-empty-string $configured
     * @param non-empty-string $expected
     */
    #[DataProvider('supportedJoinTypes')]
    public function testStructuredJoinAcceptsOnlyDocumentedJoinTypes(string $configured, string $expected): void
    {
        $driver = new QueryBuilderJoinDriverSpy();

        QueryBuilder::make($driver)
            ->table('users')
            ->joinOn('profiles', 'users.id', '=', 'profiles.user_id', $configured)
            ->get();

        self::assertStringContainsString(
            sprintf(' %s JOIN "profiles" ON "users"."id" = "profiles"."user_id"', $expected),
            $driver->lastSql(),
        );
    }

    #[DataProvider('invalidJoinTypes')]
    public function testJoinTypeInjectionIsRejected(string $type): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported JOIN type');

        QueryBuilder::make(new QueryBuilderJoinDriverSpy())
            ->table('users')
            ->joinOn('profiles', 'users.id', '=', 'profiles.user_id', $type);
    }

    #[DataProvider('invalidJoinTables')]
    public function testJoinTableAndAliasInjectionIsRejected(string $table): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsafe SQL table identifier');

        QueryBuilder::make(new QueryBuilderJoinDriverSpy())
            ->table('users')
            ->joinRaw($table, 'profiles.user_id = users.id');
    }

    public function testStructuredJoinRejectsInjectedColumnAndOperator(): void
    {
        $builder = QueryBuilder::make(new QueryBuilderJoinDriverSpy())->table('users');

        try {
            $builder->joinOn('profiles', 'users.id OR 1=1', '=', 'profiles.user_id');
            self::fail('Expected an injected JOIN column to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Unsafe SQL identifier', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported JOIN comparison operator');

        $builder->joinOn('profiles', 'users.id', '= 1 OR', 'profiles.user_id');
    }

    public function testExplicitRawJoinParameterizesUntrustedValues(): void
    {
        $driver = new QueryBuilderJoinDriverSpy();
        $untrusted = "active' OR 1=1 --";

        QueryBuilder::make($driver)
            ->table('users AS u')
            ->joinRaw(
                table: 'profiles AS p',
                condition: 'p.user_id = u.id AND p.state = ? AND p.score >= ?',
                bindings: [$untrusted, 10],
                type: 'LEFT',
            )
            ->get();

        self::assertStringNotContainsString($untrusted, $driver->lastSql());
        self::assertStringContainsString('p.state = ? AND p.score >= ?', $driver->lastSql());
        self::assertSame([$untrusted, 10], $driver->lastBindings());
    }

    public function testLegacyJoinSupportsTrailingBindingsWithoutChangingArgumentOrder(): void
    {
        $driver = new QueryBuilderJoinDriverSpy();
        $state = "enabled' OR 1=1 --";

        QueryBuilder::make($driver)
            ->table('users')
            ->join('profiles', 'profiles.user_id = users.id AND profiles.state = ?', 'INNER', [$state])
            ->get();

        self::assertStringNotContainsString($state, $driver->lastSql());
        self::assertSame([$state], $driver->lastBindings());
    }

    public function testSubqueryAndRawConditionBindingsKeepSqlPlaceholderOrder(): void
    {
        $driver = new QueryBuilderJoinDriverSpy();
        $role = "admin' OR 1=1 --";
        $state = 'active';
        $tenant = 42;
        $subquery = QueryBuilder::make($driver)
            ->table('memberships')
            ->select('user_id')
            ->where('role', $role);

        QueryBuilder::make($driver)
            ->table('users AS u')
            ->joinSubquery(
                query: $subquery,
                alias: 'm',
                condition: 'm.user_id = u.id AND m.state = ?',
                type: 'LEFT',
                conditionBindings: [$state],
            )
            ->where('u.tenant_id', $tenant)
            ->get();

        self::assertStringNotContainsString($role, $driver->lastSql());
        self::assertSame([$role, $state, $tenant], $driver->lastBindings());
    }

    public function testEmptyRawJoinConditionIsRejectedInsteadOfCompilingInvalidSql(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JOIN ON condition cannot be empty');

        QueryBuilder::make(new QueryBuilderJoinDriverSpy())
            ->table('users')
            ->joinRaw('profiles', '  ');
    }

    public function testOtherStructuredClausesRejectIdentifiersAndBindValues(): void
    {
        $driver = new QueryBuilderJoinDriverSpy();
        $untrusted = "ada@example.test' OR 1=1 --";

        QueryBuilder::make($driver)
            ->table('users')
            ->select(['users.id', 'users.email'])
            ->where('users.email', $untrusted)
            ->groupBy('users.id')
            ->having('users.id >=', 1)
            ->orderBy('users.id', 'DESC')
            ->get();

        self::assertStringNotContainsString($untrusted, $driver->lastSql());
        self::assertSame([$untrusted, 1], $driver->lastBindings());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsafe SQL identifier');

        QueryBuilder::make($driver)->table('users')->orderBy('id; DROP TABLE users');
    }

    /**
     * @return iterable<string, array{non-empty-string, non-empty-string}>
     */
    public static function supportedJoinTypes(): iterable
    {
        yield 'inner' => ['inner', 'INNER'];
        yield 'left' => ['LEFT', 'LEFT'];
        yield 'left outer' => ['left outer', 'LEFT OUTER'];
        yield 'right' => ['RIGHT', 'RIGHT'];
        yield 'right outer' => ['right outer', 'RIGHT OUTER'];
        yield 'full' => ['FULL', 'FULL'];
        yield 'full outer' => ['full outer', 'FULL OUTER'];
        yield 'cross' => ['CROSS', 'CROSS'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidJoinTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'unsupported natural' => ['NATURAL'];
        yield 'statement injection' => ['LEFT; DROP TABLE users; --'];
        yield 'join injection' => ['INNER JOIN audit ON 1=1 --'];
        yield 'comment suffix' => ['LEFT --'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidJoinTables(): iterable
    {
        yield 'statement injection' => ['profiles; DROP TABLE users'];
        yield 'condition injection' => ['profiles p ON 1=1 --'];
        yield 'derived expression' => ['(SELECT * FROM profiles) p'];
        yield 'alias suffix' => ['profiles AS p; --'];
        yield 'quoted escape' => ['profiles` AS p'];
    }
}

final class QueryBuilderJoinDriverSpy implements DatabaseDriverInterface
{
    /**
     * @var list<array{sql: string, bindings: list<mixed>}>
     */
    private array $queries = [];

    private readonly IdentifierProtector $identifiers;

    public function __construct()
    {
        $this->identifiers = new IdentifierProtector(new SqliteIdentifierEscaper(''));
    }

    public function tableExists(string $table): bool
    {
        unset($table);

        return true;
    }

    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $binds === false ? [] : array_values($binds),
        ];

        return true;
    }

    public function cursor(string $sql, array|false $binds = false): \Generator
    {
        unset($sql, $binds);

        yield from [];
    }

    public function simple_query(string $sql): bool
    {
        unset($sql);

        return true;
    }

    public function affected_rows(): int
    {
        return 0;
    }

    public function insert_id(): int|string|null
    {
        return null;
    }

    public function escape(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : get_debug_type($value);
    }

    public function escape_str(string $value, bool $like = false): string
    {
        unset($like);

        return $value;
    }

    public function escape_like_str(string $value): string
    {
        return $value;
    }

    public function escape_identifiers(string $item): string
    {
        return $this->identifiers->protect($item);
    }

    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        return $this->identifiers->protect(
            $item,
            $prefixSingle,
            $protectIdentifiers,
            $fieldExists,
        );
    }

    public function platform(): string
    {
        return 'sqlite';
    }

    public function version(): string
    {
        return 'test';
    }

    public function lastSql(): string
    {
        if ($this->queries === []) {
            throw new \LogicException('Expected QueryBuilder to execute a query.');
        }

        return $this->queries[array_key_last($this->queries)]['sql'];
    }

    /**
     * @return list<mixed>
     */
    public function lastBindings(): array
    {
        if ($this->queries === []) {
            throw new \LogicException('Expected QueryBuilder to execute a query.');
        }

        return $this->queries[array_key_last($this->queries)]['bindings'];
    }
}
