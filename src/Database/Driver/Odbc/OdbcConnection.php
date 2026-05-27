<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Throwable;

final class OdbcConnection implements ConnectionInterface
{
    /** @var resource|null */
    private $connection = null;

    private int $affectedRows = 0;

    private bool $transactionActive = false;

    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    /**
     * @return resource
     */
    public function resource(): mixed
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        if (!function_exists('odbc_connect')) {
            throw DatabaseException::connectionFailed(
                'ODBC extension is not available in current PHP runtime.',
            );
        }

        $dsn = $this->resolveDsn();
        $username = $this->config->username();
        $password = $this->config->password();

        $resource = $this->config->persistent()
            ? @odbc_pconnect($dsn, $username, $password)
            : @odbc_connect($dsn, $username, $password);

        if ($resource === false) {
            $error = $this->lastError();
            $details = $this->buildErrorDetails($error['state'], $error['message']);
            throw DatabaseException::connectionFailed(
                sprintf('Unable to connect via ODBC DSN "%s"%s', $dsn, $details),
            );
        }

        $this->connection = $resource;

        return $this->connection;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): OdbcExecutionResult
    {
        try {
            $statement = $this->executeStatement($sql, $bindings);
            $fieldCount = @odbc_num_fields($statement);
            $hasResultSet = $fieldCount > 0;

            $rows = [];
            $fields = [];

            if ($hasResultSet) {
                $fields = $this->fetchFields($statement, $fieldCount);
                $rows = $this->fetchAllRows($statement);
            }

            $affected = @odbc_num_rows($statement);
            $this->affectedRows = $affected >= 0 ? $affected : 0;
            @odbc_free_result($statement);

            return new OdbcExecutionResult(
                hasResultSet: $hasResultSet,
                rows: $rows,
                fields: $fields,
                affectedRows: $this->affectedRows,
                insertId: null,
            );
        } catch (Throwable $exception) {
            throw DatabaseException::queryFailed(
                $sql,
                $this->augmentErrorMessage($exception->getMessage()),
                $exception,
            );
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $result = $this->execute($sql, $bindings);

        return $result->rows();
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array $bindings = []): \Generator
    {
        $statement = $this->executeStatement($sql, $bindings);
        $fieldCount = @odbc_num_fields($statement);

        if ($fieldCount <= 0) {
            @odbc_free_result($statement);
            return;
        }

        try {
            while (($row = $this->odbcFetchArray($statement)) !== null) {
                yield $row;
            }
        } finally {
            @odbc_free_result($statement);
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        $this->execute($sql, $bindings);

        return $this->affectedRows;
    }

    public function beginTransaction(): void
    {
        $resource = $this->resource();

        if (@odbc_autocommit($resource, false) === false) {
            throw DatabaseException::connectionFailed(
                'Unable to disable ODBC autocommit for transaction begin.',
            );
        }

        $this->transactionActive = true;
    }

    public function commit(): void
    {
        $resource = $this->resource();

        if (@odbc_commit($resource) === false) {
            throw DatabaseException::connectionFailed('ODBC commit failed.');
        }

        @odbc_autocommit($resource, true);
        $this->transactionActive = false;
    }

    public function rollBack(): void
    {
        $resource = $this->resource();

        if (@odbc_rollback($resource) === false) {
            throw DatabaseException::connectionFailed('ODBC rollback failed.');
        }

        @odbc_autocommit($resource, true);
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

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function reconnect(): void
    {
        $this->close();
        $this->resource();
    }

    public function close(): void
    {
        if ($this->connection === null) {
            return;
        }

        @odbc_close($this->connection);
        $this->connection = null;
        $this->transactionActive = false;
    }

    public function serverVersion(): string
    {
        $resource = $this->resource();

        if (defined('SQL_DBMS_VER')) {
            if (!function_exists('odbc_getinfo')) {
                return 'unknown';
            }

            $version = @odbc_getinfo($resource, SQL_DBMS_VER);
            if (is_string($version) && $version !== '') {
                return $version;
            }
        }

        return 'unknown';
    }

    public function escapeString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * @return array{state:string|null,message:string|null}
     */
    public function lastError(): array
    {
        if ($this->connection === null) {
            return ['state' => null, 'message' => null];
        }

        $state = @odbc_error($this->connection);
        $message = @odbc_errormsg($this->connection);

        return [
            'state' => $state !== '' ? $state : null,
            'message' => $message !== '' ? $message : null,
        ];
    }

    private function resolveDsn(): string
    {
        $dsn = $this->config->dsn();
        if (is_string($dsn) && $dsn !== '') {
            return $dsn;
        }

        $host = trim($this->config->host());
        if ($host !== '') {
            return $host;
        }

        throw DatabaseException::invalidConfiguration(
            'ODBC driver requires non-empty "dsn" (or "host" as DSN fallback).',
        );
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    /**
     * @param array<int|string, mixed> $bindings
     * @return resource
     */
    private function executeStatement(string $sql, array $bindings): mixed
    {
        $resource = $this->resource();
        [$sql, $bindings] = $this->normalizeBindingsForOdbc($sql, $bindings);

        if ($bindings === []) {
            $statement = @odbc_exec($resource, $sql);
        } else {
            $statement = @odbc_prepare($resource, $sql);
            if ($statement !== false) {
                $statement = @odbc_execute($statement, $bindings) ? $statement : false;
            }
        }

        if ($statement === false) {
            $error = $this->lastError();
            $details = $this->buildErrorDetails($error['state'], $error['message']);
            throw DatabaseException::queryFailed(
                $sql,
                'ODBC execute failed' . $details,
            );
        }

        return $statement;
    }

    /**
     * ODBC works most reliably with positional placeholders.
     * Convert named placeholders (:name) to positional (?) outside quoted literals.
     *
     * @param array<int|string, mixed> $bindings
     * @return array{0:string,1:list<mixed>}
     */
    private function normalizeBindingsForOdbc(string $sql, array $bindings): array
    {
        if ($bindings === []) {
            return [$sql, []];
        }

        $containsNamed = preg_match('/:[A-Za-z_][A-Za-z0-9_]*/', $sql) === 1;
        if (!$containsNamed) {
            return [$sql, array_values($bindings)];
        }

        $isAssoc = array_keys($bindings) !== range(0, count($bindings) - 1);
        if (!$isAssoc) {
            return [$sql, array_values($bindings)];
        }

        $rewrittenSql = '';
        $orderedBindings = [];
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$inDouble) {
                if ($inSingle && $i + 1 < $length && $sql[$i + 1] === "'") {
                    $rewrittenSql .= "''";
                    $i++;
                    continue;
                }

                $inSingle = !$inSingle;
                $rewrittenSql .= $char;
                continue;
            }

            if ($char === '"' && !$inSingle) {
                if ($inDouble && $i + 1 < $length && $sql[$i + 1] === '"') {
                    $rewrittenSql .= '""';
                    $i++;
                    continue;
                }

                $inDouble = !$inDouble;
                $rewrittenSql .= $char;
                continue;
            }

            if (!$inSingle && !$inDouble && $char === ':') {
                $name = '';
                $j = $i + 1;

                while ($j < $length && preg_match('/[A-Za-z0-9_]/', $sql[$j]) === 1) {
                    $name .= $sql[$j];
                    $j++;
                }

                if ($name !== '' && preg_match('/^[A-Za-z_]/', $name) === 1) {
                    if (!array_key_exists($name, $bindings) && !array_key_exists(':' . $name, $bindings)) {
                        throw DatabaseException::queryFailed(
                            $sql,
                            sprintf('Missing named ODBC binding for placeholder :%s', $name),
                        );
                    }

                    $orderedBindings[] = $bindings[$name] ?? $bindings[':' . $name];
                    $rewrittenSql .= '?';
                    $i = $j - 1;
                    continue;
                }
            }

            $rewrittenSql .= $char;
        }

        return [$rewrittenSql, $orderedBindings];
    }

    /**
     * @param int<1, max> $fieldCount
     * @return list<OdbcField>
     */
    /**
     * @param resource $statement
     * @param int<1, max> $fieldCount
     * @return list<OdbcField>
     */
    private function fetchFields(mixed $statement, int $fieldCount): array
    {
        $fields = [];

        for ($i = 1; $i <= $fieldCount; $i++) {
            $name = @odbc_field_name($statement, $i);
            $type = @odbc_field_type($statement, $i);
            $length = @odbc_field_len($statement, $i);

            $fields[] = new OdbcField(
                name: is_string($name) ? $name : ('column_' . $i),
                type: is_string($type) ? $type : 'mixed',
                maxLength: is_int($length) ? $length : 0,
            );
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @param resource $statement
     * @return list<array<string, mixed>>
     */
    private function fetchAllRows(mixed $statement): array
    {
        $rows = [];

        while (($row = $this->odbcFetchArray($statement)) !== null) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @param resource $statement
     * @return array<string, mixed>|null
     */
    private function odbcFetchArray(mixed $statement): ?array
    {
        if (function_exists('odbc_fetch_array')) {
            $row = @odbc_fetch_array($statement);
            if (!is_array($row)) {
                return null;
            }

            return $this->normalizeAssoc($row);
        }

        $raw = [];
        if (@odbc_fetch_into($statement, $raw) === false) {
            return null;
        }
        if (!is_array($raw)) {
            return null;
        }

        $assoc = [];
        $count = count($raw);
        for ($i = 0; $i < $count; $i++) {
            $name = @odbc_field_name($statement, $i + 1);
            $assoc[is_string($name) && $name !== '' ? $name : ('column_' . ($i + 1))] = $raw[$i];
        }

        return $assoc;
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeAssoc(array $value): array
    {
        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function augmentErrorMessage(string $base): string
    {
        $error = $this->lastError();

        return $base . $this->buildErrorDetails($error['state'], $error['message']);
    }

    private function buildErrorDetails(?string $state, ?string $message): string
    {
        $parts = [];
        if ($state !== null) {
            $parts[] = 'SQLSTATE=' . $state;
        }
        if ($message !== null) {
            $parts[] = $message;
        }

        if ($parts === []) {
            return '';
        }

        return ' (' . implode('; ', $parts) . ')';
    }
}
