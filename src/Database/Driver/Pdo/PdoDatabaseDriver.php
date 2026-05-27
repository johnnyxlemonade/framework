<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Throwable;

final class PdoDatabaseDriver implements DatabaseDriverInterface
{
    /** @var array<string, mixed> */
    private array $dataCache = [];

    private bool $transEnabled = true;

    private bool $transStrict = true;

    private int $transDepth = 0;

    private bool $transStatus = true;

    private bool $transFailure = false;

    public function __construct(
        private readonly PdoConnection $connection,
        private readonly DatabaseConfig $config,
    ) {}

    public function initialize(): bool
    {
        $this->connection->pdo();

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
        return 'pdo';
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
            $this->transStatus = false;
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
            $this->transStatus = false;
            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    public function simple_query(string $sql): bool
    {
        try {
            $this->connection->statement($this->prepQuery($sql));
            return true;
        } catch (Throwable $exception) {
            $this->transStatus = false;
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

    public function trans_off(): void
    {
        $this->transEnabled = false;
    }

    public function trans_strict(bool $mode = true): void
    {
        $this->transStrict = $mode;
    }

    public function trans_start(bool $testMode = false): bool
    {
        if (!$this->transEnabled) {
            return false;
        }

        return $this->trans_begin($testMode);
    }

    public function trans_complete(): bool
    {
        if (!$this->transEnabled) {
            return false;
        }

        if ($this->transStatus === false || $this->transFailure === true) {
            $this->trans_rollback();

            if ($this->transStrict === false) {
                $this->transStatus = true;
            }

            return false;
        }

        return $this->trans_commit();
    }

    public function trans_status(): bool
    {
        return $this->transStatus;
    }

    public function trans_active(): bool
    {
        return $this->transDepth > 0;
    }

    public function trans_begin(bool $testMode = false): bool
    {
        if (!$this->transEnabled) {
            return false;
        }

        if ($this->transDepth > 0) {
            $this->transDepth++;
            return true;
        }

        $this->transFailure = $testMode;

        try {
            $this->connection->beginTransaction();
            $this->transStatus = true;
            $this->transDepth++;

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function trans_commit(): bool
    {
        if (!$this->transEnabled || $this->transDepth === 0) {
            return false;
        }

        if ($this->transDepth > 1) {
            $this->transDepth--;
            return true;
        }

        try {
            $this->connection->commit();
            $this->transDepth = 0;
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function trans_rollback(): bool
    {
        if (!$this->transEnabled || $this->transDepth === 0) {
            return false;
        }

        if ($this->transDepth > 1) {
            $this->transDepth--;
            return true;
        }

        try {
            $this->connection->rollBack();
            $this->transDepth = 0;
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function escape(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!$value instanceof \Stringable && !is_scalar($value)) {
            return "''";
        }

        return "'" . $this->escape_str((string) $value) . "'";
    }

    public function escape_str(string $value, bool $like = false): string
    {
        $escaped = $this->connection->escapeString($value);

        if ($like) {
            return $this->escape_like_str($escaped);
        }

        return $escaped;
    }

    public function escape_like_str(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    public function escape_identifiers(string $item): string
    {
        if ($item === '*') {
            return $item;
        }

        return '"' . str_replace('"', '""', $item) . '"';
    }

    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        unset($prefixSingle, $protectIdentifiers, $fieldExists);

        if ($item === '*') {
            return $item;
        }

        if (str_contains($item, '(') || str_contains($item, "'")) {
            return $item;
        }

        if (preg_match('/\s+AS\s+/i', $item) === 1) {
            $parts = preg_split('/\s+AS\s+/i', $item, 2);
            if (!is_array($parts) || count($parts) !== 2) {
                return $item;
            }

            [$field, $alias] = $parts;
            return $this->protect_identifiers($field) . ' AS ' . $this->escape_identifiers($alias);
        }

        if (str_contains($item, ' ')) {
            [$field, $alias] = explode(' ', $item, 2);
            return $this->protect_identifiers($field) . ' ' . $this->escape_identifiers(trim($alias));
        }

        if (str_contains($item, '.')) {
            return implode('.', array_map(
                fn(string $part): string => $this->escape_identifiers($part),
                explode('.', $item),
            ));
        }

        return $this->escape_identifiers($this->config->prefix() . $item);
    }

    private function prepQuery(string $sql): string
    {
        if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql) === 1) {
            return trim($sql) . ' WHERE 1=1';
        }

        return $sql;
    }
}
