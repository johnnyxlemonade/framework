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

/**
 * Executes framework database operations through the legacy MySQL connection.
 */
final class MysqlDatabaseDriver implements DatabaseDriverInterface
{
    use EscapesValues;
    use ManagesTransactions;

    /** @var array<string, mixed> */
    private array $dataCache = [];

    /**
     * Wires the MySQL connection, normalized configuration, and identifier services used by legacy driver operations.
     */
    public function __construct(
        private readonly MysqlConnectionInterface $connection,
        private readonly DatabaseConfig $config,
        private readonly IdentifierEscaperInterface $identifierEscaper,
        private readonly IdentifierProtector $identifierProtector,
    ) {
    }

    /**
     * Opens the lazy MySQL connection so startup failures surface before database work begins.
     */
    public function initialize(): bool
    {
        $this->connection->mysqli();

        return true;
    }

    /**
     * Re-establishes the underlying MySQL connection after a disconnect.
     */
    public function reconnect(): void
    {
        $this->connection->reconnect();
    }

    /**
     * Releases the underlying MySQL connection.
     */
    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * Returns the stable platform identifier used by legacy database consumers.
     */
    public function platform(): string
    {
        return 'mysql';
    }

    /**
     * Returns and caches the connected server version for the current selected database.
     */
    public function version(): string
    {
        if (isset($this->dataCache['version']) && is_string($this->dataCache['version'])) {
            return $this->dataCache['version'];
        }

        return $this->dataCache['version'] = $this->connection->serverVersion();
    }

    /**
     * Determines whether a table exists in the configured MySQL or MariaDB database.
     */
    public function tableExists(string $table): bool
    {
        return in_array($this->config->prefix() . $table, $this->list_tables(), true)
            || in_array($table, $this->list_tables(), true);
    }

    /**
     * Selects an explicit database or the configured default and clears metadata cached for the previous selection.
     */
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

    /**
     * Requests a MySQL connection character set without converting connection failures into exceptions.
     */
    public function db_set_charset(string $charset): bool
    {
        try {
            return $this->connection->setCharset($charset);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Executes a MySQL query and marks the managed transaction failed before exposing driver failures.
     *
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
     * Streams query rows and marks the managed transaction failed if cursor execution fails.
     *
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

    /**
     * Executes SQL that does not require bindings or a framework result cursor.
     */
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

    /**
     * Returns the latest affected-row count from the underlying connection.
     */
    public function affected_rows(): int
    {
        return $this->connection->affectedRows();
    }

    /**
     * Returns the latest generated insert identifier from the underlying connection.
     */
    public function insert_id(): int|string|null
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Returns the latest MySQL error code and message from the underlying connection.
     *
     * @return array{code:int|string|null,message:string|null}
     */
    public function error(): array
    {
        return $this->connection->lastError();
    }

    /**
     * Escapes a MySQL string fragment and optionally escapes LIKE wildcard characters.
     */
    public function escape_str(string $value, bool $like = false): string
    {
        $escaped = $this->connection->escapeString($value);

        if ($like) {
            return $this->escape_like_str($escaped);
        }

        return $escaped;
    }

    /**
     * Escapes an identifier using MySQL quoting rules.
     */
    public function escape_identifiers(string $item): string
    {
        return $this->identifierEscaper->identifier($item);
    }

    /**
     * Protects a compound SQL identifier according to legacy prefix and alias rules.
     */
    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        return $this->identifierProtector->protect($item, $prefixSingle, $protectIdentifiers, $fieldExists);
    }

    /**
     * Returns the first column reported by MySQL metadata or false when no columns exist.
     */
    public function primary(string $table): string|false
    {
        $fields = $this->list_fields($table);

        return $fields !== [] ? $fields[0] : false;
    }

    /**
     * Counts rows in a validated table and normalizes the database aggregate to an integer.
     */
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
     * Lists MySQL table names and caches the unfiltered metadata for the selected database.
     *
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

    /**
     * Retains the legacy snake_case table lookup API.
     */
    public function table_exists(string $tableName): bool
    {
        return $this->tableExists($tableName);
    }

    /**
     * Lists columns for one table through the active MySQL connection.
     *
     * @return list<string>
     */
    public function list_fields(string $table): array
    {
        return $this->connection->listColumns($table);
    }

    /**
     * Determines whether a table exposes a named column in its current MySQL metadata.
     */
    public function field_exists(string $fieldName, string $tableName): bool
    {
        return in_array($fieldName, $this->list_fields($tableName), true);
    }

    /**
     * Builds legacy-compatible field metadata from MySQL SHOW COLUMNS rows.
     *
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

    /**
     * Makes bare DELETE statements explicit so they retain legacy framework semantics.
     */
    private function prepQuery(string $sql): string
    {
        if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql) === 1) {
            return trim($sql) . ' WHERE 1=1';
        }

        return $sql;
    }

    /**
     * Begins the physical transaction used by the shared transaction concern.
     */
    protected function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    /**
     * Commits the physical transaction used by the shared transaction concern.
     */
    protected function commitTransaction(): void
    {
        $this->connection->commit();
    }

    /**
     * Rolls back the physical transaction used by the shared transaction concern.
     */
    protected function rollbackTransaction(): void
    {
        $this->connection->rollBack();
    }
}
