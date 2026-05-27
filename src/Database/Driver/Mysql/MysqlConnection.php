<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;
use mysqli;
use mysqli_driver;
use mysqli_result;
use mysqli_stmt;
use Throwable;

final class MysqlConnection implements ConnectionInterface, MysqlConnectionInterface
{
    private ?mysqli $connection = null;

    private int $affectedRows = 0;

    private bool $transactionActive = false;

    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    public function mysqli(): mysqli
    {
        if ($this->connection instanceof mysqli) {
            return $this->connection;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): MysqlExecutionResult
    {
        try {
            if ($bindings === []) {
                $result = $this->mysqli()->query($sql);

                $this->affectedRows = (int) $this->mysqli()->affected_rows;

                return new MysqlExecutionResult(
                    result: $result,
                    affectedRows: $this->affectedRows,
                    insertId: $this->mysqli()->insert_id,
                );
            }

            $statement = $this->executePrepared($sql, $bindings);
            $result = $statement->get_result();

            $this->affectedRows = (int) $statement->affected_rows;
            $insertId = $this->mysqli()->insert_id;

            $statement->close();

            return new MysqlExecutionResult(
                result: $result instanceof mysqli_result ? $result : true,
                affectedRows: $this->affectedRows,
                insertId: $insertId,
            );
        } catch (Throwable $exception) {
            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    /**
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
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->execute($sql, $bindings)->affectedRows();
    }

    public function beginTransaction(): void
    {
        $this->mysqli()->begin_transaction();
        $this->transactionActive = true;
    }

    public function commit(): void
    {
        $this->mysqli()->commit();
        $this->transactionActive = false;
    }

    public function rollBack(): void
    {
        $this->mysqli()->rollback();
        $this->transactionActive = false;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    public function transaction(callable $callback): mixed
    {
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

    public function lastInsertId(): int|string
    {
        return $this->mysqli()->insert_id;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

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

    public function close(): void
    {
        if (!$this->connection instanceof mysqli) {
            return;
        }

        $this->connection->close();
        $this->connection = null;
        $this->transactionActive = false;
    }

    public function serverVersion(): string
    {
        return $this->mysqli()->server_info;
    }

    public function setCharset(string $charset): bool
    {
        return $this->mysqli()->set_charset($charset);
    }

    public function escapeString(string $value): string
    {
        return $this->mysqli()->real_escape_string($value);
    }

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

    private function escapeIdentifierPart(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $value);

        return is_string($sanitized) ? $sanitized : '';
    }
}
