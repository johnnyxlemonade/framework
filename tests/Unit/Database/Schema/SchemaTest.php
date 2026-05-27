<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Schema;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testCreateExecutesCompiledSqlAndReturnValueReflectsDriverResult(): void
    {
        $grammar = new RecordingSchemaGrammar();
        $compiler = new SchemaCompiler($grammar);
        $db = new RecordingDatabaseDriver();
        $schema = new Schema($compiler, $db);

        self::assertTrue($schema->create('users', static function ($table): void {
            $table->string('name');
        }, true));
        self::assertSame(['SQL_CREATE_users_1'], $db->queries);

        $db->failOn = 'SQL_CREATE_users_1';
        self::assertFalse($schema->create('users', static function ($table): void {
            $table->string('name');
        }, true));
    }

    public function testCreateTableDropRenameCreateDatabaseDropDatabaseExecuteQueries(): void
    {
        $compiler = new SchemaCompiler(new RecordingSchemaGrammar());
        $db = new RecordingDatabaseDriver();
        $schema = new Schema($compiler, $db);

        self::assertTrue($schema->createTable(TableDefinition::create('x')));
        self::assertTrue($schema->drop('x', true));
        self::assertTrue($schema->rename('x', 'y'));
        self::assertTrue($schema->createDatabase('demo'));
        self::assertTrue($schema->dropDatabase('demo'));

        self::assertSame(
            ['SQL_CREATE_x_0', 'SQL_DROP_x_1', 'SQL_RENAME_x_y', 'SQL_CREATE_DB_demo', 'SQL_DROP_DB_demo'],
            $db->queries,
        );
    }

    public function testTableExecutesAllStatementsAndStopsOnFirstFailure(): void
    {
        $grammar = new RecordingSchemaGrammar();
        $grammar->alterStatements = ['A1', 'A2', 'A3'];
        $db = new RecordingDatabaseDriver();
        $schema = new Schema(new SchemaCompiler($grammar), $db);

        self::assertTrue($schema->table('users', static function ($table): void {
            $table->integer('age');
        }));
        self::assertSame(['A1', 'A2', 'A3'], $db->queries);

        $db2 = new RecordingDatabaseDriver();
        $db2->failOn = 'A2';
        $schema2 = new Schema(new SchemaCompiler($grammar), $db2);
        self::assertFalse($schema2->table('users', static function ($table): void {
            $table->integer('age');
        }));
        self::assertSame(['A1', 'A2'], $db2->queries);
    }

    public function testCompileMethodsDelegateToCompilerAndDoNotExecuteQueries(): void
    {
        $compiler = new SchemaCompiler(new RecordingSchemaGrammar());
        $db = new RecordingDatabaseDriver();
        $schema = new Schema($compiler, $db);

        self::assertSame('SQL_CREATE_users_0', $schema->compileCreate('users', static function ($table): void {
            $table->string('name');
        }));
        self::assertSame(['SQL_ALTER_users_1'], $schema->compileTable('users', static function ($table): void {
            $table->integer('age');
        }));
        self::assertSame('SQL_DROP_users_0', $schema->compileDrop('users'));
        self::assertSame('SQL_RENAME_old_new', $schema->compileRename('old', 'new'));

        self::assertSame([], $db->queries);
    }
}

final class RecordingDatabaseDriver implements DatabaseDriverInterface
{
    /** @var list<string> */
    public array $queries = [];
    public ?string $failOn = null;

    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        unset($binds);
        $this->queries[] = $sql;

        if ($this->failOn !== null && $sql === $this->failOn) {
            return false;
        }

        return new EmptyDatabaseResult();
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
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
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
        return $item;
    }

    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        unset($prefixSingle, $protectIdentifiers, $fieldExists);

        return $item;
    }

    public function platform(): string
    {
        return 'test';
    }

    public function version(): string
    {
        return '1';
    }
}

final class EmptyDatabaseResult implements DatabaseResultInterface
{
    public function num_rows(): int
    {
        return 0;
    }
    public function num_fields(): int
    {
        return 0;
    }
    public function list_fields(): array
    {
        return [];
    }
    public function field_data(): array
    {
        return [];
    }
    public function result(string $type = 'object'): array
    {
        unset($type);
        return [];
    }
    public function result_object(): array
    {
        return [];
    }
    public function result_array(): array
    {
        return [];
    }
    public function custom_result_object(string $className): array
    {
        unset($className);
        return [];
    }
    public function row(int|string $n = 0, string $type = 'object'): mixed
    {
        unset($n, $type);
        return null;
    }
    public function row_array(int $n = 0): ?array
    {
        unset($n);
        return null;
    }
    public function row_object(int $n = 0): ?object
    {
        unset($n);
        return null;
    }
    public function first_row(string $type = 'object'): mixed
    {
        unset($type);
        return null;
    }
    public function last_row(string $type = 'object'): mixed
    {
        unset($type);
        return null;
    }
    public function next_row(string $type = 'object'): mixed
    {
        unset($type);
        return null;
    }
    public function previous_row(string $type = 'object'): mixed
    {
        unset($type);
        return null;
    }
    public function data_seek(int $n = 0): bool
    {
        unset($n);
        return false;
    }
    public function free_result(): void {}
}
