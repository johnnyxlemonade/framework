<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;
use Stringable;
use Throwable;

final class PdoConnection implements ConnectionInterface
{
    private ?PDO $connection = null;

    private int $affectedRows = 0;

    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    public function pdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $this->connection = new PDO(
                dsn: PdoDsnResolver::resolve($this->config),
                username: $this->config->username(),
                password: $this->config->password(),
                options: $this->resolveOptions(),
            );
        } catch (Throwable $exception) {
            throw DatabaseException::connectionFailed($exception->getMessage(), $exception);
        }

        return $this->connection;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->execute($sql, $bindings);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        /** @var list<array<string, mixed>> $normalized */
        $normalized = [];

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

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array $bindings = []): \Generator
    {
        $statement = $this->execute($sql, $bindings);

        try {
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (!is_array($row)) {
                    continue;
                }

                $assoc = [];
                foreach ($row as $key => $value) {
                    if (is_string($key)) {
                        $assoc[$key] = $value;
                    }
                }

                yield $assoc;
            }
        } finally {
            $statement->closeCursor();
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        $statement = $this->execute($sql, $bindings);

        return $this->affectedRows = $statement->rowCount();
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo()->beginTransaction()) {
            throw DatabaseException::connectionFailed('Unable to begin PDO transaction.');
        }
    }

    public function commit(): void
    {
        if (!$this->pdo()->commit()) {
            throw DatabaseException::connectionFailed('Unable to commit PDO transaction.');
        }
    }

    public function rollBack(): void
    {
        if (!$this->pdo()->rollBack()) {
            throw DatabaseException::connectionFailed('Unable to rollback PDO transaction.');
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo()->inTransaction();
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
        $value = $this->pdo()->lastInsertId();

        if ($value === false || $value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        return $value;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function reconnect(): void
    {
        $this->close();
        $this->pdo();
    }

    public function close(): void
    {
        $this->connection = null;
    }

    public function serverVersion(): string
    {
        $version = $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        return is_string($version) && $version !== '' ? $version : 'unknown';
    }

    public function escapeString(string $value): string
    {
        $quoted = $this->pdo()->quote($value);

        if (!is_string($quoted) || strlen($quoted) < 2) {
            return str_replace("'", "''", $value);
        }

        return substr($quoted, 1, -1);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);

            if (!$statement instanceof PDOStatement) {
                throw DatabaseException::queryFailed($sql, 'Unable to prepare PDO statement.');
            }

            $this->bindValues($statement, $bindings, $sql);
            $statement->execute();
            $this->affectedRows = $statement->rowCount();

            return $statement;
        } catch (PDOException $exception) {
            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        } catch (Throwable $exception) {
            throw DatabaseException::queryFailed($sql, $exception->getMessage(), $exception);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function resolveOptions(): array
    {
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $normalized = [];

        foreach ($this->config->options() as $key => $value) {
            if (is_int($key)) {
                $normalized[$key] = $value;
                continue;
            }

            if (ctype_digit($key)) {
                $normalized[(int) $key] = $value;
            }
        }

        $options = $normalized + $defaults;

        if ($this->config->persistent()) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        return $options;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function bindValues(PDOStatement $statement, array $bindings, string $sql): void
    {
        if ($bindings === []) {
            return;
        }

        if (array_is_list($bindings)) {
            foreach ($bindings as $index => $value) {
                $statement->bindValue($index + 1, $this->normalizeBindingValue($value, $sql), $this->detectPdoType($value));
            }

            return;
        }

        foreach ($bindings as $name => $value) {
            $normalizedName = ltrim((string) $name, ':');

            if ($normalizedName === '') {
                throw DatabaseException::queryFailed($sql, 'Invalid named PDO binding key.');
            }

            $statement->bindValue(':' . $normalizedName, $this->normalizeBindingValue($value, $sql), $this->detectPdoType($value));
        }
    }

    private function detectPdoType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            is_resource($value) => PDO::PARAM_LOB,
            default => PDO::PARAM_STR,
        };
    }

    private function normalizeBindingValue(mixed $value, string $sql): mixed
    {
        if (
            is_null($value)
            || is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value)
            || is_resource($value)
        ) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw DatabaseException::queryFailed($sql, 'Unsupported PDO binding value type.');
    }
}
