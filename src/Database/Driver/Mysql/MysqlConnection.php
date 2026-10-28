<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use mysqli;
use mysqli_driver;
use mysqli_result;
use mysqli_stmt;
use Throwable;

/**
 * Owns a lazily connected MySQLi session, parameter binding, transaction state, and optional database instrumentation.
 */
final class MysqlConnection implements ConnectionInterface, MysqlConnectionInterface
{
    private ?mysqli $connection = null;

    private int $affectedRows = 0;

    private bool $transactionActive = false;

    /**
     * Creates a lazy MySQLi adapter from normalized connection settings and shared optional database instrumentation.
     */
    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly DatabaseInstrumentation $instrumentation = new DatabaseInstrumentation(),
    ) {
    }

    /**
     * Returns the live MySQLi handle, creating and configuring it only on first use.
     *
     * @throws DatabaseException When connection setup fails.
     */
    public function mysqli(): mysqli
    {
        if ($this->connection instanceof mysqli) {
            return $this->connection;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        return $this->instrumentation->connection(function (): mysqli {
            try {
                $driver = new mysqli_driver();
                $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

                $connection = mysqli_init();

                if (!$connection instanceof mysqli) {
                    throw DatabaseException::connectionFailed('Unable to initialize mysqli.');
                }

                $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

                $host = $this->config->persistent()
                    ? 'p:' . $this->config->host()
                    : $this->config->host();

                $connection->real_connect(
                    hostname: $host,
                    username: $this->config->username(),
                    password: $this->config->password(),
                    database: $this->config->database(),
                    port: $this->config->port(),
                );

                $connection->set_charset($this->config->charset());

                $this->configureSession($connection);

                $this->connection = $connection;

                return $this->connection;
            } catch (Throwable $exception) {
                throw DatabaseException::connectionFailed($exception->getMessage(), $exception);
            }
        });
    }

    /**
     * Executes SQL directly or as a prepared statement and records its timing and write metadata.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): MysqlExecutionResult
    {
        try {
            $connection = $this->mysqli();
        } catch (Throwable $exception) {
            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }

        return $this->instrumentation->query($sql, function () use ($connection, $sql, $bindings): MysqlExecutionResult {
            try {
                if ($bindings === []) {
                    $result = $connection->query($sql);

                    $this->affectedRows = (int) $connection->affected_rows;

                    return new MysqlExecutionResult(
                        result: $result,
                        affectedRows: $this->affectedRows,
                        insertId: $this->mysqli()->insert_id,
                    );
                }

                $statement = $this->executePrepared($sql, $bindings);
                $result = $statement->get_result();

                $this->affectedRows = (int) $statement->affected_rows;
                $insertId = $connection->insert_id;

                $statement->close();

                return new MysqlExecutionResult(
                    result: $result instanceof mysqli_result ? $result : true,
                    affectedRows: $this->affectedRows,
                    insertId: $insertId,
                );
            } catch (Throwable $exception) {
                throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
            }
        });
    }

    /**
     * Materializes a result-producing query and releases its native result buffer afterwards.
     *
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $executionResult = $this->execute($sql, $bindings);
        $result = $executionResult->mysqliResult();

        if (!$result instanceof mysqli_result) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        $result->free();

        return $rows;
    }

    /**
     * Streams rows from a native result buffer and always frees it when iteration finishes.
     *
     * @param array<int|string, mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array $bindings = []): \Generator
    {
        $executionResult = $this->execute($sql, $bindings);
        $result = $executionResult->mysqliResult();

        if (!$result instanceof mysqli_result) {
            return;
        }

        try {
            while (($row = $result->fetch_assoc()) !== null) {
                if (!is_array($row)) {
                    continue;
                }

                yield $row;
            }
        } finally {
            $result->free();
        }
    }

    /**
     * Executes a statement and returns the affected-row count captured by execute().
     *
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->execute($sql, $bindings)->affectedRows();
    }

    /**
     * Begins a physical MySQL transaction and records it as active for nested transaction users.
     */
    public function beginTransaction(): void
    {
        $this->mysqli()->begin_transaction();
        $this->transactionActive = true;
    }

    /**
     * Commits the active MySQL transaction and clears local transaction state.
     */
    public function commit(): void
    {
        $this->mysqli()->commit();
        $this->transactionActive = false;
    }

    /**
     * Rolls back the active MySQL transaction and clears local transaction state.
     */
    public function rollBack(): void
    {
        $this->mysqli()->rollback();
        $this->transactionActive = false;
    }

    /**
     * Reports whether this adapter currently owns an active physical MySQL transaction.
     */
    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    /**
     * Executes a callback atomically, reusing an existing transaction rather than opening a nested physical one.
     *
     * @template T
     * @param callable(self):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->inTransaction()) {
            return $callback($this);
        }

        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Returns the latest auto-generated identifier reported by the MySQLi handle.
     */
    public function lastInsertId(): int|string
    {
        return $this->mysqli()->insert_id;
    }

    /**
     * Returns the affected-row count captured by the latest execution through this adapter.
     */
    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * Verifies the existing handle or replaces it after a failed ping.
     */
    public function reconnect(): void
    {
        if (!$this->connection instanceof mysqli) {
            $this->mysqli();

            return;
        }

        try {
            if ($this->connection->ping()) {
                return;
            }
        } catch (Throwable) {
            // Reconnect below.
        }

        $this->connection->close();
        $this->connection = null;
        $this->transactionActive = false;

        $this->mysqli();
    }

    /**
     * Closes the live MySQLi handle and clears local connection and transaction state.
     */
    public function close(): void
    {
        if (!$this->connection instanceof mysqli) {
            return;
        }

        $this->connection->close();
        $this->connection = null;
        $this->transactionActive = false;
    }

    /**
     * Returns the server version from the current MySQLi handle.
     */
    public function serverVersion(): string
    {
        return $this->mysqli()->server_info;
    }

    /**
     * Requests a connection character set from MySQLi.
     */
    public function setCharset(string $charset): bool
    {
        return $this->mysqli()->set_charset($charset);
    }

    /**
     * Escapes a value for a MySQL string literal without surrounding quotes.
     */
    public function escapeString(string $value): string
    {
        return $this->mysqli()->real_escape_string($value);
    }

    /**
     * Selects a database and translates MySQLi failures to the framework connection exception.
     */
    public function selectDatabase(string $database): void
    {
        try {
            $this->mysqli()->select_db($database);
        } catch (Throwable $exception) {
            throw DatabaseException::connectionFailed(
                sprintf('Unable to select database [%s]: %s', $database, $exception->getMessage()),
                $exception,
            );
        }
    }

    /**
     * Lists tables in the configured database and can constrain the result to the configured prefix.
     *
     * @return list<string>
     */
    public function listTables(bool $prefixLimit = false): array
    {
        $sql = sprintf(
            'SHOW TABLES FROM `%s`',
            $this->escapeIdentifierPart($this->config->database()),
        );

        if ($prefixLimit && $this->config->prefix() !== '') {
            $sql .= sprintf(
                " LIKE '%s%%'",
                $this->escapeString($this->config->prefix()),
            );
        }

        $rows = $this->select($sql);

        $tables = [];

        foreach ($rows as $row) {
            $value = reset($row);

            if (is_string($value) && $value !== '') {
                $tables[] = $value;
            }
        }

        return $tables;
    }

    /**
     * Lists column names through MySQL SHOW COLUMNS metadata.
     *
     * @return list<string>
     */
    public function listColumns(string $table): array
    {
        $rows = $this->select(sprintf(
            'SHOW COLUMNS FROM `%s`',
            $this->escapeIdentifierPart($table),
        ));

        $columns = [];

        foreach ($rows as $row) {
            $field = $row['Field'] ?? null;

            if (is_string($field) && $field !== '') {
                $columns[] = $field;
            }
        }

        return $columns;
    }

    /**
     * Returns the latest native MySQLi error while tolerating an unopened connection.
     *
     * @return array{code:int|string|null,message:string|null}
     */
    public function lastError(): array
    {
        if (!$this->connection instanceof mysqli) {
            return [
                'code' => null,
                'message' => null,
            ];
        }

        return [
            'code' => $this->connection->errno,
            'message' => $this->connection->error !== '' ? $this->connection->error : null,
        ];
    }

    /**
     * Prepares, binds, and executes SQL when bindings require a native MySQLi statement.
     *
     * @param array<int|string, mixed> $bindings
     */
    private function executePrepared(string $sql, array $bindings): mysqli_stmt
    {
        try {
            $statement = $this->mysqli()->prepare($sql);
            if (!$statement instanceof mysqli_stmt) {
                throw DatabaseException::queryFailed($sql, 'Unable to prepare mysqli statement.');
            }

            if ($bindings !== []) {
                $values = array_values($bindings);
                $types = $this->detectBindingTypes($values);

                $statement->bind_param($types, ...$values);
            }

            $statement->execute();

            return $statement;
        } catch (Throwable $exception) {
            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    /**
     * Maps PHP values to MySQLi bind type characters in positional binding order.
     *
     * @param list<mixed> $values
     */
    private function detectBindingTypes(array $values): string
    {
        $types = '';

        foreach ($values as $value) {
            $types .= match (true) {
                is_int($value) => 'i',
                is_float($value) => 'd',
                is_resource($value) => 'b',
                default => 's',
            };
        }

        return $types;
    }

    /**
     * Applies configured strict mode and collation options after MySQLi connects.
     */
    private function configureSession(mysqli $connection): void
    {
        if ($this->config->collation() !== '') {
            $connection->query(sprintf(
                'SET NAMES %s COLLATE %s',
                $this->escapeIdentifierPart($this->config->charset()),
                $this->escapeIdentifierPart($this->config->collation()),
            ));
        }

        if ($this->config->strict()) {
            $connection->query(
                "SET SESSION sql_mode = TRIM(BOTH ',' FROM IF("
                . "FIND_IN_SET('STRICT_TRANS_TABLES', REPLACE(@@sql_mode, ' ', '')) > 0, "
                . '@@sql_mode, '
                . "CONCAT_WS(',', @@sql_mode, 'STRICT_TRANS_TABLES')"
                . '))',
            );

            return;
        }

        $connection->query(
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM "
            . 'REPLACE(REPLACE(REPLACE('
            . "CONCAT(',', REPLACE(@@sql_mode, ' ', ''), ','), "
            . "',STRICT_TRANS_TABLES,', ','), "
            . "',STRICT_ALL_TABLES,', ','), "
            . "',ONLY_FULL_GROUP_BY,', ','))",
        );
    }

    /**
     * Escapes a single identifier segment used by MySQL metadata statements.
     */
    private function escapeIdentifierPart(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $value);

        return is_string($sanitized) ? $sanitized : '';
    }
}
