<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Driver\Concerns\EscapesValues;
use Lemonade\Framework\Database\Driver\Concerns\ManagesTransactions;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Sql\IdentifierEscaperInterface;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use mysqli_result;
use Throwable;

final class MysqlDatabaseDriver implements DatabaseDriverInterface
{
    use EscapesValues;
    use ManagesTransactions;

    /** @var array<string, mixed> */
    private array $dataCache = [];

    public function __construct(
        private readonly MysqlConnectionInterface $connection,
        private readonly DatabaseConfig $config,
        private readonly IdentifierEscaperInterface $identifierEscaper,
        private readonly IdentifierProtector $identifierProtector,
    ) {}

    public function initialize(): bool
    {
        $this->connection->mysqli();

        return true;
    }

    public function reconnect(): void
    {
        $this->connection->reconnect();
    }

    public function close(): void
    {
        $this->connection->close();
    }

    public function platform(): string
    {
        return 'mysql';
    }

    public function version(): string
    {
        if (isset($this->dataCache['version']) && is_string($this->dataCache['version'])) {
            return $this->dataCache['version'];
        }

        return $this->dataCache['version'] = $this->connection->serverVersion();
    }

    public function db_select(string $database = ''): bool
    {
        $database = $database !== '' ? $database : $this->config->database();

        try {
            $this->connection->selectDatabase($database);
            $this->dataCache = [];

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function db_set_charset(string $charset): bool
    {
        try {
            return $this->connection->setCharset($charset);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<int|string, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false): MysqlResult|bool
    {
        try {
            $preparedSql = $this->prepQuery($sql);

            $executionResult = $this->connection->execute(
                sql: $preparedSql,
                bindings: $binds === false ? [] : $binds,
            );

            $result = $executionResult->mysqliResult();

            if ($result instanceof mysqli_result) {
                return MysqlResult::fromMysqliResult($result);
            }

            return true;
        } catch (Throwable $exception) {
            $this->markTransactionFailure();

            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    /**
     * @param array<int|string, mixed>|false $binds
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array|false $binds = false): \Generator
    {
        $preparedSql = $this->prepQuery($sql);

        try {
            yield from $this->connection->cursor(
                sql: $preparedSql,
                bindings: $binds === false ? [] : $binds,
            );
        } catch (Throwable $exception) {
            $this->markTransactionFailure();

            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    public function simple_query(string $sql): bool
    {
        try {
            $this->connection->execute(
                sql: $this->prepQuery($sql),
            );

            return true;
        } catch (Throwable $exception) {
            $this->markTransactionFailure();

            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    public function affected_rows(): int
    {
        return $this->connection->affectedRows();
    }

    public function insert_id(): int|string|null
    {
        return $this->connection->lastInsertId();
    }

    /**
     * @return array{code:int|string|null,message:string|null}
     */
    public function error(): array
    {
        return $this->connection->lastError();
    }

    public function escape_str(string $value, bool $like = false): string
    {
        $escaped = $this->connection->escapeString($value);

        if ($like) {
            return $this->escape_like_str($escaped);
        }

        return $escaped;
    }

    public function escape_identifiers(string $item): string
    {
        return $this->identifierEscaper->identifier($item);
    }

    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        return $this->identifierProtector->protect($item, $prefixSingle, $protectIdentifiers, $fieldExists);
    }

    public function primary(string $table): string|false
    {
        $fields = $this->list_fields($table);

        return $fields !== [] ? $fields[0] : false;
    }

    public function count_all(string $table = ''): int
    {
        if ($table === '') {
            return 0;
        }

        $result = $this->query(
            'SELECT COUNT(*) AS ' . $this->escape_identifiers('numrows') .
            ' FROM ' . $this->protect_identifiers($table, true, null, false),
        );

        if (!$result instanceof DatabaseResultInterface || $result->num_rows() === 0) {
            return 0;
        }

        $row = $result->row();

        if (!is_object($row)) {
            return 0;
        }

        $numRows = $row->numrows ?? 0;

        if (is_int($numRows)) {
            return $numRows;
        }

        if (is_float($numRows)) {
            return (int) $numRows;
        }

        if (is_string($numRows) && is_numeric($numRows)) {
            return (int) $numRows;
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    public function list_tables(bool $constrainByPrefix = false): array
    {
        if (isset($this->dataCache['table_names']) && is_array($this->dataCache['table_names'])) {
            /** @var list<string> $tables */
            $tables = $this->dataCache['table_names'];

            return $tables;
        }

        return $this->dataCache['table_names'] = $this->connection->listTables($constrainByPrefix);
    }

    public function table_exists(string $tableName): bool
    {
        return in_array($this->config->prefix() . $tableName, $this->list_tables(), true)
            || in_array($tableName, $this->list_tables(), true);
    }

    /**
     * @return list<string>
     */
    public function list_fields(string $table): array
    {
        return $this->connection->listColumns($table);
    }

    public function field_exists(string $fieldName, string $tableName): bool
    {
        return in_array($fieldName, $this->list_fields($tableName), true);
    }

    /**
     * @return list<MysqlField>
     */
    public function field_data(string $table): array
    {
        $result = $this->query(
            'SHOW COLUMNS FROM ' . $this->protect_identifiers($table, true, null, false),
        );

        if (!$result instanceof MysqlResult) {
            return [];
        }

        $rows = $result->result_object();
        $fields = [];

        foreach ($rows as $row) {
            $type = '';
            $maxLength = 0;

            if (isset($row->Type) && is_string($row->Type)) {
                if (preg_match('/^([a-z]+)(?:\((\d+)\))?/i', $row->Type, $matches) === 1) {
                    $type = strtolower($matches[1]);
                    $maxLength = isset($matches[2]) ? (int) $matches[2] : 0;
                }
            }

            $fields[] = new MysqlField(
                name: is_string($row->Field ?? null) ? $row->Field : '',
                type: $type,
                maxLength: $maxLength,
                primaryKey: ($row->Key ?? null) === 'PRI',
                default: $row->Default ?? null,
            );
        }

        return $fields;
    }

    private function prepQuery(string $sql): string
    {
        if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql) === 1) {
            return trim($sql) . ' WHERE 1=1';
        }

        return $sql;
    }

    protected function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    protected function commitTransaction(): void
    {
        $this->connection->commit();
    }

    protected function rollbackTransaction(): void
    {
        $this->connection->rollBack();
    }
}
