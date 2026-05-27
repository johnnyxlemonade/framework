<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

final class QueryBuilder
{
    /**
     * @var list<string>
     */
    private array $select = ['*'];

    private string $from = '';
    private bool $distinct = false;

    /**
     * @var list<mixed>
     */
    private array $selectBindings = [];

    /**
     * @var list<mixed>
     */
    private array $fromBindings = [];

    /**
     * @var list<array{type:string,table:string,condition:string,bindings:list<mixed>}>
     */
    private array $joins = [];

    /**
     * @var list<array{boolean:string,sql:string,bindings:list<mixed>}>
     */
    private array $where = [];

    /**
     * @var list<string>
     */
    private array $groupBy = [];

    /**
     * @var list<string>
     */
    private array $orderBy = [];
    /**
     * @var list<array{boolean:string,sql:string,bindings:list<mixed>}>
     */
    private array $having = [];

    private ?int $limit = null;
    private ?int $offset = null;
    private ?string $lockClause = null;
    /**
     * @var list<array{all:bool,sql:string,bindings:list<mixed>}>
     */
    private array $unions = [];

    /**
     * @param array<string, mixed> $set
     */
    private function __construct(
        private readonly DatabaseDriverInterface $db,
        private array $set = [],
    ) {}

    public static function make(DatabaseDriverInterface $db): self
    {
        return new self($db);
    }

    public function table(string $table): self
    {
        $next = clone $this;
        $table = trim($table);

        if (preg_match(
            '/^([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)(?:\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*))?$/i',
            $table,
            $m,
        ) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Unsafe SQL table identifier "%s". Use fromRaw() or fromSubquery() for explicit expressions.',
                $table,
            ));
        }

        $next->from = $this->protectTable($m[1]);

        if (isset($m[2])) {
            $next->from .= ' AS ' . $this->protectIdentifier($m[2]);
        }

        return $next;
    }

    public function from(string $table): self
    {
        return $this->table($table);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function fromRaw(string $sql, array $bindings = []): self
    {
        $sql = trim($sql);

        if ($sql === '') {
            throw new \InvalidArgumentException('Raw FROM expression cannot be empty.');
        }

        $next = clone $this;
        $next->from = $sql;
        $next->fromBindings = $bindings;

        return $next;
    }

    /**
     * @param string|list<string> $columns
     */
    public function select(string|array $columns = '*'): self
    {
        $next = clone $this;
        $list = is_array($columns) ? $columns : [$columns];

        if ($list === []) {
            return $next;
        }

        $next->select = array_map(
            fn(string $col): string => $this->protectColumn($col),
            $list,
        );
        $next->selectBindings = [];

        return $next;
    }

    public function selectSubquery(self $query, string $alias): self
    {
        [$sql, $bindings] = $query->compileSelect();
        $alias = $this->protectIdentifier($alias);

        return $this->selectRaw('(' . $sql . ') AS ' . $alias, $bindings);
    }

    public function fromSubquery(self $query, string $alias): self
    {
        [$sql, $bindings] = $query->compileSelect();

        $next = clone $this;
        $next->from = '(' . $sql . ') AS ' . $this->protectIdentifier($alias);
        $next->fromBindings = $bindings;

        return $next;
    }

    /**
     * @param list<mixed> $bindings
     */
    public function selectRaw(string $sql, array $bindings = []): self
    {
        $next = clone $this;
        $raw = trim($sql);
        if ($raw === '') {
            return $next;
        }

        if ($next->select === ['*']) {
            $next->select = [];
        }

        $next->select[] = $raw;
        $next->selectBindings = array_merge($next->selectBindings, $bindings);

        return $next;
    }

    public function distinct(bool $state = true): self
    {
        $next = clone $this;
        $next->distinct = $state;

        return $next;
    }

    public function join(string $table, string $condition, string $type = 'INNER'): self
    {
        $next = clone $this;

        $next->joins[] = [
            'type' => ($normalizedType = strtoupper(trim($type))) !== '' ? $normalizedType : 'INNER',
            'table' => $this->protectTable($table),
            'condition' => $condition,
            'bindings' => [],
        ];

        return $next;
    }

    public function joinSubquery(self $query, string $alias, string $condition, string $type = 'INNER'): self
    {
        [$sql, $bindings] = $query->compileSelect();

        $next = clone $this;

        $next->joins[] = [
            'type' => ($normalizedType = strtoupper(trim($type))) !== '' ? $normalizedType : 'INNER',
            'table' => '(' . $sql . ') AS ' . $this->protectIdentifier($alias),
            'condition' => $condition,
            'bindings' => $bindings,
        ];

        return $next;
    }

    public function leftJoinSubquery(self $query, string $alias, string $condition): self
    {
        return $this->joinSubquery($query, $alias, $condition, 'LEFT');
    }

    /**
     * @param string|array<string, mixed> $column
     */
    public function where(string|array $column, mixed $value = null): self
    {
        return $this->addWhere('AND', $column, $value);
    }

    /**
     * @param string|array<string, mixed> $column
     */
    public function orWhere(string|array $column, mixed $value = null): self
    {
        return $this->addWhere('OR', $column, $value);
    }

    public function where_like(string $column, string $value, string $side = 'both'): self
    {
        return $this->whereLike($column, $value, $side);
    }

    public function whereLike(string $column, string $value, string $side = 'both'): self
    {
        return $this->addWhereLike('AND', $column, $value, $side, false);
    }

    public function or_where_like(string $column, string $value, string $side = 'both'): self
    {
        return $this->orWhereLike($column, $value, $side);
    }

    public function orWhereLike(string $column, string $value, string $side = 'both'): self
    {
        return $this->addWhereLike('OR', $column, $value, $side, false);
    }

    public function where_not_like(string $column, string $value, string $side = 'both'): self
    {
        return $this->whereNotLike($column, $value, $side);
    }

    public function whereNotLike(string $column, string $value, string $side = 'both'): self
    {
        return $this->addWhereLike('AND', $column, $value, $side, true);
    }

    public function or_where_not_like(string $column, string $value, string $side = 'both'): self
    {
        return $this->orWhereNotLike($column, $value, $side);
    }

    public function orWhereNotLike(string $column, string $value, string $side = 'both'): self
    {
        return $this->addWhereLike('OR', $column, $value, $side, true);
    }

    /**
     * @param list<mixed> $values
     */
    public function where_in(string $column, array $values): self
    {
        return $this->whereIn($column, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        return $this->addWhereIn('AND', $column, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function where_not_in(string $column, array $values): self
    {
        return $this->whereNotIn($column, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->addWhereIn('AND', $column, $values, true);
    }

    /**
     * @param list<mixed> $values
     */
    public function or_where_in(string $column, array $values): self
    {
        return $this->orWhereIn($column, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->addWhereIn('OR', $column, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function or_where_not_in(string $column, array $values): self
    {
        return $this->orWhereNotIn($column, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->addWhereIn('OR', $column, $values, true);
    }

    public function whereInSubquery(string $column, self $query): self
    {
        return $this->addWhereInSubquery('AND', $column, $query, false);
    }

    public function whereNotInSubquery(string $column, self $query): self
    {
        return $this->addWhereInSubquery('AND', $column, $query, true);
    }

    public function orWhereInSubquery(string $column, self $query): self
    {
        return $this->addWhereInSubquery('OR', $column, $query, false);
    }

    public function orWhereNotInSubquery(string $column, self $query): self
    {
        return $this->addWhereInSubquery('OR', $column, $query, true);
    }

    public function where_null(string $column): self
    {
        return $this->whereNull($column);
    }

    public function whereNull(string $column): self
    {
        return $this->addWhereNull('AND', $column, false);
    }

    public function where_not_null(string $column): self
    {
        return $this->whereNotNull($column);
    }

    public function whereNotNull(string $column): self
    {
        return $this->addWhereNull('AND', $column, true);
    }

    public function or_where_null(string $column): self
    {
        return $this->orWhereNull($column);
    }

    public function orWhereNull(string $column): self
    {
        return $this->addWhereNull('OR', $column, false);
    }

    public function or_where_not_null(string $column): self
    {
        return $this->orWhereNotNull($column);
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->addWhereNull('OR', $column, true);
    }

    public function where_between(string $column, mixed $from, mixed $to): self
    {
        return $this->whereBetween($column, $from, $to);
    }

    public function whereBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addWhereBetween('AND', $column, $from, $to, false);
    }

    public function where_not_between(string $column, mixed $from, mixed $to): self
    {
        return $this->whereNotBetween($column, $from, $to);
    }

    public function whereNotBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addWhereBetween('AND', $column, $from, $to, true);
    }

    public function or_where_between(string $column, mixed $from, mixed $to): self
    {
        return $this->orWhereBetween($column, $from, $to);
    }

    public function orWhereBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addWhereBetween('OR', $column, $from, $to, false);
    }

    public function or_where_not_between(string $column, mixed $from, mixed $to): self
    {
        return $this->orWhereNotBetween($column, $from, $to);
    }

    public function orWhereNotBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addWhereBetween('OR', $column, $from, $to, true);
    }

    public function when(bool $condition, callable $then, ?callable $else = null): self
    {
        if ($condition) {
            $result = $then($this);

            return $result instanceof self ? $result : $this;
        }

        if ($else !== null) {
            $result = $else($this);

            return $result instanceof self ? $result : $this;
        }

        return $this;
    }

    /**
     * @param list<mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        return $this->addWhereRaw('AND', $sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->addWhereRaw('OR', $sql, $bindings);
    }

    public function order_by(string $column, string $direction = 'ASC'): self
    {
        return $this->orderBy($column, $direction);
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $next = clone $this;
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $next->orderBy[] = $this->protectIdentifier($column) . ' ' . $dir;

        return $next;
    }

    /**
     * @param string|list<string> $columns
     */
    public function group_by(string|array $columns): self
    {
        return $this->groupBy($columns);
    }

    /**
     * @param string|list<string> $columns
     */
    public function groupBy(string|array $columns): self
    {
        $next = clone $this;
        $list = is_array($columns) ? $columns : [$columns];
        foreach ($list as $column) {
            $next->groupBy[] = $this->protectIdentifier($column);
        }

        return $next;
    }

    /**
     * @param string|array<string, mixed> $column
     */
    public function having(string|array $column, mixed $value = null): self
    {
        return $this->addHaving('AND', $column, $value);
    }

    /**
     * @param string|array<string, mixed> $column
     */
    public function orHaving(string|array $column, mixed $value = null): self
    {
        return $this->addHaving('OR', $column, $value);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function havingRaw(string $sql, array $bindings = []): self
    {
        return $this->addHavingRaw('AND', $sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function orHavingRaw(string $sql, array $bindings = []): self
    {
        return $this->addHavingRaw('OR', $sql, $bindings);
    }

    /**
     * @param list<mixed> $values
     */
    public function havingIn(string $column, array $values): self
    {
        return $this->addHavingIn('AND', $column, $values, false);
    }

    /**
     * @param list<mixed> $values
     */
    public function orHavingIn(string $column, array $values): self
    {
        return $this->addHavingIn('OR', $column, $values, false);
    }

    /**
     * @param list<mixed> $values
     */
    public function havingNotIn(string $column, array $values): self
    {
        return $this->addHavingIn('AND', $column, $values, true);
    }

    /**
     * @param list<mixed> $values
     */
    public function orHavingNotIn(string $column, array $values): self
    {
        return $this->addHavingIn('OR', $column, $values, true);
    }

    public function havingBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addHavingBetween('AND', $column, $from, $to, false);
    }

    public function orHavingBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addHavingBetween('OR', $column, $from, $to, false);
    }

    public function havingNotBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addHavingBetween('AND', $column, $from, $to, true);
    }

    public function orHavingNotBetween(string $column, mixed $from, mixed $to): self
    {
        return $this->addHavingBetween('OR', $column, $from, $to, true);
    }

    public function limit(int $limit, int $offset = 0): self
    {
        $next = clone $this;
        $next->limit = max(0, $limit);
        $next->offset = max(0, $offset);

        return $next;
    }

    public function offset(int $offset): self
    {
        $next = clone $this;
        $next->offset = max(0, $offset);

        return $next;
    }

    public function union(self $query): self
    {
        return $this->addUnion($query, false);
    }

    public function unionAll(self $query): self
    {
        return $this->addUnion($query, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function set(array $data): self
    {
        $next = clone $this;
        $next->set = $data;

        return $next;
    }

    public function get(?int $limit = null, int $offset = 0): DatabaseResultInterface|bool
    {
        $builder = $limit !== null ? $this->limit($limit, $offset) : $this;
        [$sql, $bindings] = $builder->compileSelect();

        return $this->db->query($sql, $bindings);
    }

    /**
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(?int $limit = null, int $offset = 0): \Generator
    {
        $builder = $limit !== null ? $this->limit($limit, $offset) : $this;
        [$sql, $bindings] = $builder->compileSelect();

        yield from $this->dbCursor($sql, $bindings);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getArray(?int $limit = null, int $offset = 0): array
    {
        $result = $this->get($limit, $offset);
        if (!$result instanceof DatabaseResultInterface) {
            return [];
        }

        return $result->result_array();
    }

    public function exists(): bool
    {
        $row = $this
            ->selectRaw('1 AS __exists')
            ->limit(1)
            ->first();

        return $row !== null;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function value(string $column): mixed
    {
        $alias = '__value';

        $row = $this
            ->selectRaw($this->protectColumn($column) . ' AS ' . $this->protectIdentifier($alias))
            ->limit(1)
            ->first();

        if (!is_array($row)) {
            return null;
        }

        return $row[$alias] ?? null;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $valueAlias = '__pluck_value';

        if ($key === null) {
            $rows = $this
                ->selectRaw($this->protectColumn($column) . ' AS ' . $this->protectIdentifier($valueAlias))
                ->getArray();

            $out = [];

            foreach ($rows as $row) {
                if (array_key_exists($valueAlias, $row)) {
                    $out[] = $row[$valueAlias];
                }
            }

            return $out;
        }

        $keyAlias = '__pluck_key';

        $rows = $this
            ->selectRaw($this->protectColumn($column) . ' AS ' . $this->protectIdentifier($valueAlias))
            ->selectRaw($this->protectColumn($key) . ' AS ' . $this->protectIdentifier($keyAlias))
            ->getArray();

        $out = [];

        foreach ($rows as $row) {
            if (!array_key_exists($valueAlias, $row)) {
                continue;
            }

            if (!array_key_exists($keyAlias, $row)) {
                continue;
            }

            $keyValue = $row[$keyAlias];
            if (!is_scalar($keyValue) && !$keyValue instanceof \Stringable) {
                continue;
            }

            $out[(string) $keyValue] = $row[$valueAlias];
        }

        return $out;
    }

    public function lockForUpdate(): self
    {
        $next = clone $this;
        $next->lockClause = 'FOR UPDATE';

        return $next;
    }

    public function sharedLock(): self
    {
        $next = clone $this;
        $next->lockClause = 'LOCK IN SHARE MODE';

        return $next;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $rows = $this->limit(1)->getArray();

        return $rows[0] ?? null;
    }

    public function count_all_results(): int
    {
        return $this->countAllResults();
    }

    public function countAllResults(): int
    {
        [$sql, $bindings] = $this->compileSelect('COUNT(*) AS numrows');
        $result = $this->db->query($sql, $bindings);
        if (!$result instanceof DatabaseResultInterface) {
            return 0;
        }

        $row = $result->row_array();

        $numrows = $row['numrows'] ?? 0;
        if (is_int($numrows)) {
            return $numrows;
        }
        if (is_float($numrows)) {
            return (int) $numrows;
        }
        if (is_string($numrows) && is_numeric($numrows)) {
            return (int) $numrows;
        }

        return 0;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function insert(?array $data = null): bool
    {
        $payload = $data ?? $this->set;
        if ($payload === [] || $this->from === '') {
            return false;
        }

        $columns = array_keys($payload);
        $sql = 'INSERT INTO ' . $this->from
            . ' (' . implode(', ', array_map(fn(string $c): string => $this->protectIdentifier($c), $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $result = $this->db->query($sql, array_values($payload));

        return $result !== false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function batch_insert(array $rows): bool
    {
        return $this->batchInsert($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function batchInsert(array $rows): bool
    {
        if ($this->from === '' || $rows === []) {
            return false;
        }

        $columns = array_keys($rows[0]);
        if ($columns === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                return false;
            }
        }

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valuesSql = implode(', ', array_fill(0, count($rows), $placeholders));

        $bindings = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }

        $sql = 'INSERT INTO ' . $this->from
            . ' (' . implode(', ', array_map(fn(string $c): string => $this->protectIdentifier($c), $columns)) . ')'
            . ' VALUES ' . $valuesSql;

        $result = $this->db->query($sql, $bindings);

        return $result !== false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function insertOrIgnore(array $rows): bool
    {
        if ($this->from === '' || $rows === []) {
            return false;
        }

        $columns = array_keys($rows[0]);
        if ($columns === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                return false;
            }
        }

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valuesSql = implode(', ', array_fill(0, count($rows), $placeholders));
        $bindings = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }

        $sql = 'INSERT IGNORE INTO ' . $this->from
            . ' (' . implode(', ', array_map(fn(string $c): string => $this->protectIdentifier($c), $columns)) . ')'
            . ' VALUES ' . $valuesSql;

        $result = $this->db->query($sql, $bindings);

        return $result !== false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $updateColumns
     */
    public function upsert(array $rows, array $updateColumns = []): bool
    {
        if ($this->from === '' || $rows === []) {
            return false;
        }

        $columns = array_keys($rows[0]);
        if ($columns === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                return false;
            }
        }

        $updates = $updateColumns !== []
            ? $updateColumns
            : array_values(array_filter($columns, fn(string $c): bool => $c !== 'id'));

        if ($updates === []) {
            return false;
        }

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valuesSql = implode(', ', array_fill(0, count($rows), $placeholders));
        $bindings = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }

        $updateSql = implode(', ', array_map(
            fn(string $column): string => $this->protectIdentifier($column)
                . ' = VALUES(' . $this->protectIdentifier($column) . ')',
            $updates,
        ));

        $sql = 'INSERT INTO ' . $this->from
            . ' (' . implode(', ', array_map(fn(string $c): string => $this->protectIdentifier($c), $columns)) . ')'
            . ' VALUES ' . $valuesSql
            . ' ON DUPLICATE KEY UPDATE ' . $updateSql;

        $result = $this->db->query($sql, $bindings);

        return $result !== false;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function update(?array $data = null): bool
    {
        $payload = $data ?? $this->set;
        if ($payload === [] || $this->from === '') {
            return false;
        }
        if ($this->where === []) {
            return false;
        }

        $set = [];
        $bindings = [];
        foreach ($payload as $column => $value) {
            $set[] = $this->protectIdentifier($column) . ' = ?';
            $bindings[] = $value;
        }

        [$whereSql, $whereBindings] = $this->compileWhere();
        $sql = 'UPDATE ' . $this->from . ' SET ' . implode(', ', $set) . $whereSql;
        $bindings = array_merge($bindings, $whereBindings);

        $result = $this->db->query($sql, $bindings);

        return $result !== false;
    }

    public function increment(string $column, int|float $amount = 1): bool
    {
        return $this->applyArithmeticUpdate($column, '+', $amount);
    }

    public function decrement(string $column, int|float $amount = 1): bool
    {
        return $this->applyArithmeticUpdate($column, '-', $amount);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function batch_update(array $rows, string $index): bool
    {
        return $this->batchUpdate($rows, $index);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function batchUpdate(array $rows, string $index): bool
    {
        if ($this->from === '' || $rows === []) {
            return false;
        }

        $index = trim($index);
        if ($index === '') {
            return false;
        }

        $first = $rows[0];
        if (!array_key_exists($index, $first)) {
            return false;
        }

        $columns = array_values(array_filter(
            array_keys($first),
            static fn(string $column): bool => $column !== $index,
        ));

        if ($columns === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (!array_key_exists($index, $row)) {
                return false;
            }

            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    return false;
                }
            }
        }

        $protectedIndex = $this->protectColumn($index);
        $setParts = [];
        $bindings = [];

        foreach ($columns as $column) {
            $protectedColumn = $this->protectIdentifier($column);
            $caseSql = $protectedColumn . ' = CASE ' . $protectedIndex;

            foreach ($rows as $row) {
                $caseSql .= ' WHEN ? THEN ?';
                $bindings[] = $row[$index];
                $bindings[] = $row[$column];
            }

            $caseSql .= ' ELSE ' . $protectedColumn . ' END';
            $setParts[] = $caseSql;
        }

        $indexValues = array_map(
            static fn(array $row): mixed => $row[$index],
            $rows,
        );

        $inPlaceholders = implode(', ', array_fill(0, count($indexValues), '?'));

        $sql = 'UPDATE ' . $this->from
            . ' SET ' . implode(', ', $setParts)
            . ' WHERE ' . $protectedIndex . ' IN (' . $inPlaceholders . ')';

        $bindings = array_merge($bindings, $indexValues);

        $result = $this->db->query($sql, $bindings);

        return $result !== false;
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(array $where = []): bool
    {
        $builder = $where !== [] ? $this->where($where) : $this;
        if ($builder->from === '') {
            return false;
        }
        if ($builder->where === []) {
            return false;
        }

        [$whereSql, $bindings] = $builder->compileWhere();
        $sql = 'DELETE FROM ' . $builder->from . $whereSql;

        $result = $this->db->query($sql, $bindings);

        return $result !== false;
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function compileSelect(?string $selectOverride = null): array
    {
        if ($this->from === '') {
            throw new \LogicException('Cannot compile SELECT query without table/from clause.');
        }

        $select = $selectOverride ?? implode(', ', $this->select);
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . $select . ' FROM ' . $this->from;
        $bindings = array_merge(
            $this->selectBindings,
            $this->fromBindings,
        );

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join['type'] . ' JOIN ' . $join['table'] . ' ON ' . $join['condition'];
            $bindings = array_merge($bindings, $join['bindings']);
        }

        [$whereSql, $whereBindings] = $this->compileWhere();
        $sql .= $whereSql;
        $bindings = array_merge($bindings, $whereBindings);

        if ($this->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        [$havingSql, $havingBindings] = $this->compileHaving();
        $sql .= $havingSql;
        $bindings = array_merge($bindings, $havingBindings);

        if ($this->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->unions !== []) {
            $baseSql = '(' . $sql . ')';
            foreach ($this->unions as $union) {
                $baseSql .= $union['all'] ? ' UNION ALL ' : ' UNION ';
                $baseSql .= '(' . $union['sql'] . ')';
                $bindings = array_merge($bindings, $union['bindings']);
            }
            $sql = $baseSql;
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null && $this->offset > 0) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        if ($this->lockClause !== null && $this->lockClause !== '') {
            $sql .= ' ' . $this->lockClause;
        }

        return [$sql, $bindings];
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function compileWhere(): array
    {
        if ($this->where === []) {
            return ['', []];
        }

        $sql = ' WHERE ';
        $bindings = [];

        foreach ($this->where as $index => $item) {
            if ($index > 0) {
                $sql .= ' ' . $item['boolean'] . ' ';
            }

            $sql .= $item['sql'];
            $bindings = array_merge($bindings, $item['bindings']);
        }

        return [$sql, $bindings];
    }

    /**
     * @param string|array<string, mixed> $column
     */
    private function addWhere(string $boolean, string|array $column, mixed $value): self
    {
        $next = clone $this;

        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $next = $next->addWhere($boolean, $key, $val);
            }

            return $next;
        }

        $operator = '=';
        $field = trim($column);
        if (preg_match('/^(.+?)\s+(>=|<=|<>|!=|=|>|<|LIKE)$/i', $field, $m) === 1) {
            $field = trim($m[1]);
            $operator = strtoupper($m[2]);
        }

        $protected = $this->protectIdentifier($field);

        if ($value === null) {
            $sql = $protected . ($operator === '!=' || $operator === '<>' ? ' IS NOT NULL' : ' IS NULL');
            $bindings = [];
        } else {
            $sql = $protected . ' ' . $operator . ' ?';
            $bindings = [$value];
        }

        $next->where[] = [
            'boolean' => $boolean,
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $next;
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function compileHaving(): array
    {
        if ($this->having === []) {
            return ['', []];
        }

        $sql = ' HAVING ';
        $bindings = [];

        foreach ($this->having as $index => $item) {
            if ($index > 0) {
                $sql .= ' ' . $item['boolean'] . ' ';
            }

            $sql .= $item['sql'];
            $bindings = array_merge($bindings, $item['bindings']);
        }

        return [$sql, $bindings];
    }

    /**
     * @param string|array<string, mixed> $column
     */
    private function addHaving(string $boolean, string|array $column, mixed $value): self
    {
        $next = clone $this;

        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $next = $next->addHaving($boolean, $key, $val);
            }

            return $next;
        }

        $operator = '=';
        $field = trim($column);
        if (preg_match('/^(.+?)\s+(>=|<=|<>|!=|=|>|<|LIKE)$/i', $field, $m) === 1) {
            $field = trim($m[1]);
            $operator = strtoupper($m[2]);
        }

        $protected = $this->protectIdentifier($field);

        if ($value === null) {
            $sql = $protected . ($operator === '!=' || $operator === '<>' ? ' IS NOT NULL' : ' IS NULL');
            $bindings = [];
        } else {
            $sql = $protected . ' ' . $operator . ' ?';
            $bindings = [$value];
        }

        $next->having[] = [
            'boolean' => $boolean,
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $next;
    }

    /**
     * @param list<mixed> $bindings
     */
    private function addHavingRaw(string $boolean, string $sql, array $bindings = []): self
    {
        $next = clone $this;
        $trimmed = trim($sql);
        if ($trimmed === '') {
            return $next;
        }

        $next->having[] = [
            'boolean' => $boolean,
            'sql' => '(' . $trimmed . ')',
            'bindings' => $bindings,
        ];

        return $next;
    }

    /**
     * @param list<mixed> $values
     */
    private function addHavingIn(string $boolean, string $column, array $values, bool $not): self
    {
        $next = clone $this;
        if ($values === []) {
            return $next;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $next->having[] = [
            'boolean' => $boolean,
            'sql' => $this->protectIdentifier($column) . ($not ? ' NOT IN (' : ' IN (') . $placeholders . ')',
            'bindings' => $values,
        ];

        return $next;
    }

    private function addHavingBetween(string $boolean, string $column, mixed $from, mixed $to, bool $not): self
    {
        $next = clone $this;
        $next->having[] = [
            'boolean' => $boolean,
            'sql' => $this->protectIdentifier($column) . ($not ? ' NOT BETWEEN ? AND ?' : ' BETWEEN ? AND ?'),
            'bindings' => [$from, $to],
        ];

        return $next;
    }

    /**
     * @param list<mixed> $values
     */
    private function addWhereIn(string $boolean, string $column, array $values, bool $not = false): self
    {
        $next = clone $this;
        if ($values === []) {
            return $next;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $next->where[] = [
            'boolean' => $boolean,
            'sql' => $this->protectIdentifier($column) . ($not ? ' NOT IN (' : ' IN (') . $placeholders . ')',
            'bindings' => $values,
        ];

        return $next;
    }

    private function addWhereNull(string $boolean, string $column, bool $not): self
    {
        $next = clone $this;
        $next->where[] = [
            'boolean' => $boolean,
            'sql' => $this->protectIdentifier($column) . ($not ? ' IS NOT NULL' : ' IS NULL'),
            'bindings' => [],
        ];

        return $next;
    }

    private function addWhereBetween(string $boolean, string $column, mixed $from, mixed $to, bool $not): self
    {
        $next = clone $this;
        $next->where[] = [
            'boolean' => $boolean,
            'sql' => $this->protectIdentifier($column) . ($not ? ' NOT BETWEEN ? AND ?' : ' BETWEEN ? AND ?'),
            'bindings' => [$from, $to],
        ];

        return $next;
    }

    private function addWhereLike(string $boolean, string $column, string $value, string $side, bool $not): self
    {
        $next = clone $this;
        $wildcarded = $this->wildcardValue($value, $side);
        $next->where[] = [
            'boolean' => $boolean,
            'sql' => $this->protectIdentifier($column) . ($not ? ' NOT LIKE ?' : ' LIKE ?'),
            'bindings' => [$wildcarded],
        ];

        return $next;
    }

    private function addWhereInSubquery(string $boolean, string $column, self $query, bool $not): self
    {
        $next = clone $this;
        [$sql, $bindings] = $query->compileSelect();
        $next->where[] = [
            'boolean' => $boolean,
            'sql' => $this->protectIdentifier($column) . ($not ? ' NOT IN (' : ' IN (') . $sql . ')',
            'bindings' => $bindings,
        ];

        return $next;
    }

    private function addUnion(self $query, bool $all): self
    {
        $next = clone $this;
        [$sql, $bindings] = $query->compileSelect();
        $next->unions[] = [
            'all' => $all,
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $next;
    }

    /**
     * @param list<mixed> $bindings
     */
    private function addWhereRaw(string $boolean, string $sql, array $bindings = []): self
    {
        $next = clone $this;
        $trimmed = trim($sql);
        if ($trimmed === '') {
            return $next;
        }

        $next->where[] = [
            'boolean' => $boolean,
            'sql' => '(' . $trimmed . ')',
            'bindings' => $bindings,
        ];

        return $next;
    }

    private function protectTable(string $table): string
    {
        return $this->db->protect_identifiers($table, true, null, false);
    }

    private function protectColumn(string $column): string
    {
        $column = trim($column);

        if ($column === '*') {
            return '*';
        }

        return $this->protectIdentifier($column);
    }

    /**
     * @param list<mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    private function dbCursor(string $sql, array $bindings): \Generator
    {
        yield from $this->db->cursor($sql, $bindings);
    }

    private function applyArithmeticUpdate(string $column, string $operator, int|float $amount): bool
    {
        if ($this->from === '') {
            return false;
        }
        if ($this->where === []) {
            return false;
        }

        $amount = abs($amount);
        if ($amount === 0.0) {
            return true;
        }

        $protected = $this->protectIdentifier($column);
        [$whereSql, $whereBindings] = $this->compileWhere();

        $sql = 'UPDATE ' . $this->from
            . ' SET ' . $protected . ' = ' . $protected . ' ' . $operator . ' ?'
            . $whereSql;

        $bindings = array_merge([$amount], $whereBindings);
        $result = $this->db->query($sql, $bindings);

        return $result !== false;
    }

    private function protectIdentifier(string $column): string
    {
        $column = trim($column);

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $column) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Unsafe SQL identifier "%s". Use whereRaw/selectRaw for explicit expressions.',
                $column,
            ));
        }

        return $this->db->protect_identifiers($column, true, null, false);
    }

    private function wildcardValue(string $value, string $side): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        $side = strtolower(trim($side));

        return match ($side) {
            'none' => $escaped,
            'before', 'left' => '%' . $escaped,
            'after', 'right' => $escaped . '%',
            default => '%' . $escaped . '%',
        };
    }
}
