<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Driver\Concerns\EscapesValues;
use Lemonade\Framework\Database\Driver\Concerns\ManagesTransactions;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Sql\IdentifierEscaperInterface;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Throwable;

/**
 * Executes framework database operations through a PDO connection.
 */
/**
 * Provides SQL execution and identifier handling for generic PDO-backed connections.
 */
final class PdoDatabaseDriver implements DatabaseDriverInterface
{
    use EscapesValues;
    use ManagesTransactions;

    /** @var array<string, mixed> */
    private array $dataCache = [];

    private readonly IdentifierProtector $identifierProtector;

    /**
     * Initializes this PdoDatabaseDriver with the collaborators and state required by its database contract.
     */
    public function __construct(
        private readonly PdoConnection $connection,
        private readonly IdentifierEscaperInterface $identifierEscaper,
        IdentifierProtector $identifierProtector,
    ) {
        $this->identifierProtector = $identifierProtector;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function initialize(): bool
    {
        $this->connection->pdo();

        return true;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function reconnect(): void
    {
        $this->connection->reconnect();
    }

    /**
     * Releases resources retained by this database object so they cannot be reused.
     */
    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function platform(): string
    {
        return 'pdo';
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function version(): string
    {
        if (isset($this->dataCache['version']) && is_string($this->dataCache['version'])) {
            return $this->dataCache['version'];
        }

        return $this->dataCache['version'] = $this->connection->serverVersion();
    }

    /**
     * Determines whether a table exists for the connected PDO database dialect.
     */
    public function tableExists(string $table): bool
    {
        $driverName = $this->connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $sql = match ($driverName) {
            'mysql' => 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            'sqlite' => "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1",
            default => throw DatabaseException::unsupportedDriver('pdo/' . $driverName),
        };

        return $this->connection->select($sql, [$table]) !== [];
    }

    /**
     * @param array<int|string, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        try {
            $preparedSql = $this->prepQuery($sql);
            $statement = $this->connection->execute(
                sql: $preparedSql,
                bindings: $binds === false ? [] : $binds,
            );

            if ($statement->columnCount() > 0) {
                $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $normalized = [];

                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        $assoc = [];
                        foreach ($row as $key => $value) {
                            if (is_string($key)) {
                                $assoc[$key] = $value;
                            }
                        }

                        $normalized[] = $assoc;
                    }
                }

                /** @var list<array<string, mixed>> $normalized */
                return PdoResult::fromRows($normalized);
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function simple_query(string $sql): bool
    {
        try {
            $this->connection->statement($this->prepQuery($sql));
            return true;
        } catch (Throwable $exception) {
            $this->markTransactionFailure();
            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function affected_rows(): int
    {
        return $this->connection->affectedRows();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function insert_id(): int|string|null
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Escapes the supplied value or identifier for safe inclusion in driver-generated SQL.
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
     * Escapes the supplied value or identifier for safe inclusion in driver-generated SQL.
     */
    public function escape_identifiers(string $item): string
    {
        return $this->identifierEscaper->identifier($item);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
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
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    private function prepQuery(string $sql): string
    {
        if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql) === 1) {
            return trim($sql) . ' WHERE 1=1';
        }

        return $sql;
    }

    /**
     * Performs the requested transaction transition on the active database connection.
     */
    protected function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    /**
     * Performs the requested transaction transition on the active database connection.
     */
    protected function commitTransaction(): void
    {
        $this->connection->commit();
    }

    /**
     * Performs the requested transaction transition on the active database connection.
     */
    protected function rollbackTransaction(): void
    {
        $this->connection->rollBack();
    }
}
