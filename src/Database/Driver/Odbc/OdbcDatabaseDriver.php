<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Driver\Concerns\EscapesValues;
use Lemonade\Framework\Database\Driver\Concerns\ManagesTransactions;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Sql\IdentifierEscaperInterface;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Throwable;

final class OdbcDatabaseDriver implements DatabaseDriverInterface
{
    use EscapesValues;
    use ManagesTransactions;

    /** @var array<string, mixed> */
    private array $dataCache = [];

    public function __construct(
        private readonly OdbcConnection $connection,
        private readonly IdentifierEscaperInterface $identifierEscaper,
        private readonly IdentifierProtector $identifierProtector,
    ) {}

    public function initialize(): bool
    {
        $this->connection->resource();

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
        return 'odbc';
    }

    public function version(): string
    {
        if (isset($this->dataCache['version']) && is_string($this->dataCache['version'])) {
            return $this->dataCache['version'];
        }

        return $this->dataCache['version'] = $this->connection->serverVersion();
    }

    /**
     * @param array<int|string, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        try {
            $preparedSql = $this->prepQuery($sql);
            $executionResult = $this->connection->execute(
                sql: $preparedSql,
                bindings: $binds === false ? [] : $binds,
            );

            if ($executionResult->hasResultSet()) {
                return OdbcResult::fromRows(
                    $executionResult->rows(),
                    $executionResult->fields(),
                );
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
            $executionResult = $this->connection->execute($this->prepQuery($sql));

            return !$executionResult->hasResultSet();
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
