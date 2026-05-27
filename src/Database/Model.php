<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

abstract class Model
{
    protected string $table;

    protected string $primaryKey = 'id';

    protected bool $useTimestamps = false;

    protected bool $useUserTracking = false;

    protected int|string|null $actorId = null;

    protected string $createdAt = 'created_at';

    protected string $updatedAt = 'updated_at';
    protected string $createdBy = 'created_by';

    protected string $updatedBy = 'updated_by';
    protected bool $useSoftDeletes = false;

    protected string $deletedAt = 'deleted_at';

    /**
     * @var list<string>
     */
    protected array $allowedFields = [];

    protected bool $allowCallbacks = true;

    /**
     * @var list<string>
     */
    protected array $beforeInsert = [];

    /**
     * @var list<string>
     */
    protected array $afterInsert = [];

    /**
     * @var list<string>
     */
    protected array $beforeUpdate = [];

    /**
     * @var list<string>
     */
    protected array $afterUpdate = [];

    /**
     * @var list<string>
     */
    protected array $beforeInsertBatch = [];

    /**
     * @var list<string>
     */
    protected array $afterInsertBatch = [];

    /**
     * @var list<string>
     */
    protected array $beforeUpdateBatch = [];

    /**
     * @var list<string>
     */
    protected array $afterUpdateBatch = [];

    /**
     * @var list<string>
     */
    protected array $beforeFind = [];

    /**
     * @var list<string>
     */
    protected array $afterFind = [];

    /**
     * @var list<string>
     */
    protected array $beforeDelete = [];

    /**
     * @var list<string>
     */
    protected array $afterDelete = [];

    private bool $tempWithDeleted = false;

    private bool $tempOnlyDeleted = false;

    public function __construct(
        protected readonly DatabaseDriverInterface $db,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $context = [
            'operation' => 'all',
            'id' => null,
            'where' => [],
            'limit' => null,
        ];
        $context = $this->trigger('beforeFind', $context);

        $rows = $this->builder()->getArray();

        $context['result'] = $rows;
        $context = $this->trigger('afterFind', $context);

        return $this->normalizeRowList($context['result'] ?? null, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $id): ?array
    {
        $context = [
            'operation' => 'find',
            'id' => $id,
            'where' => [$this->primaryKey => $id],
            'limit' => 1,
        ];
        $context = $this->trigger('beforeFind', $context);

        $row = $this->builder()
            ->where($this->primaryKey, $id)
            ->first();

        $context['result'] = $row;
        $context = $this->trigger('afterFind', $context);

        $result = $context['result'] ?? $row;

        return $this->normalizeAssocRow($result);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>|null
     */
    public function first(array $where = []): ?array
    {
        $context = [
            'operation' => 'first',
            'id' => null,
            'where' => $where,
            'limit' => 1,
        ];
        $context = $this->trigger('beforeFind', $context);

        $rows = $this->where($where, 1);

        $row = $rows[0] ?? null;

        $context['result'] = $row;
        $context = $this->trigger('afterFind', $context);

        $result = $context['result'] ?? $row;

        return $this->normalizeAssocRow($result);
    }

    /**
     * @param array<string, mixed> $where
     * @return list<array<string, mixed>>
     */
    public function where(array $where, ?int $limit = null): array
    {
        $context = [
            'operation' => 'where',
            'id' => null,
            'where' => $where,
            'limit' => $limit,
        ];
        $context = $this->trigger('beforeFind', $context);

        $builder = $this->builder()->where($where);
        $rows = $limit !== null
            ? $builder->getArray(max(1, $limit))
            : $builder->getArray();

        $context['result'] = $rows;
        $context = $this->trigger('afterFind', $context);

        return $this->normalizeRowList($context['result'] ?? null, $rows);
    }

    /**
     * @param array<string, mixed> $where
     */
    public function exists(array $where): bool
    {
        return $this->first($where) !== null;
    }

    public function countAll(): int
    {
        return $this->builder()->countAllResults();
    }

    /**
     * @param array<string, mixed> $where
     */
    public function countWhere(array $where): int
    {
        return $this->builder()
            ->where($where)
            ->countAllResults();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): int|string|bool|null
    {
        $id = $data[$this->primaryKey] ?? null;

        if ($id === null || $id === '') {
            return $this->insert($data);
        }

        unset($data[$this->primaryKey]);

        $normalizedId = $this->normalizeIdentifier($id);
        if ($normalizedId === null) {
            return false;
        }

        return $this->update($normalizedId, $data);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function firstOrCreate(array $attributes, array $values = []): array
    {
        $existing = $this->first($attributes);
        if ($existing !== null) {
            return $existing;
        }

        $payload = array_merge($attributes, $values);
        $id = $this->insert($payload);

        if ($id === null) {
            return [];
        }

        if ($id === 0 || $id === '0') {
            return $this->first($attributes) ?? [];
        }

        return $this->find($id) ?? [];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function updateOrCreate(array $attributes, array $values = []): array
    {
        $existing = $this->first($attributes);
        if ($existing !== null) {
            $id = $existing[$this->primaryKey] ?? null;
            $normalizedId = $this->normalizeIdentifier($id);
            if ($normalizedId !== null) {
                $this->update($normalizedId, $values);

                return $this->find($normalizedId) ?? [];
            }
        }

        return $this->firstOrCreate($attributes, $values);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(array $data, ?string $uniqueBy = null): int|string|bool|null
    {
        $key = $uniqueBy !== null && trim($uniqueBy) !== '' ? trim($uniqueBy) : $this->primaryKey;
        $value = $data[$key] ?? null;

        if ($value === null || $value === '') {
            return $this->insert($data);
        }

        if ($key === $this->primaryKey) {
            return $this->save($data);
        }

        $exists = $this->withDeleted()->exists([$key => $value]);
        $payload = $data;
        unset($payload[$this->primaryKey]);
        $payload = $this->prepareUpdateData($payload);

        if ($payload === []) {
            return false;
        }

        if ($exists) {
            $ok = $this->builder()
                ->where($key, $value)
                ->set($payload)
                ->update();

            return $ok && $this->db->affected_rows() >= 0;
        }

        return $this->insert($data);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function batchUpsert(array $rows, ?string $uniqueBy = null): bool
    {
        if ($rows === []) {
            return false;
        }

        $allOk = true;

        foreach ($rows as $row) {
            $result = $this->upsert($row, $uniqueBy);
            if ($result === false || $result === null) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int|string|null
    {
        $data = $this->prepareInsertData($data);
        $context = $this->trigger('beforeInsert', ['data' => $data]);
        $data = is_array($context['data'] ?? null) ? $this->normalizeAssoc($context['data']) : $data;

        if ($data === []) {
            return null;
        }

        $ok = $this->builder()
            ->set($data)
            ->insert();

        $id = null;
        if ($ok) {
            $id = $this->db->insert_id();

            if ($id === null && $this->db->affected_rows() > 0) {
                // Some drivers (e.g. ODBC over non-identity backends) cannot provide insert id.
                // Keep successful insert distinguishable from failure for firstOrCreate/updateOrCreate flows.
                $id = 0;
            }
        }
        $this->trigger('afterInsert', [
            'id' => $id,
            'data' => $data,
            'result' => $ok,
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): bool
    {
        $data = $this->prepareUpdateData($data);
        $context = $this->trigger('beforeUpdate', [
            'id' => $id,
            'data' => $data,
        ]);
        $data = is_array($context['data'] ?? null) ? $this->normalizeAssoc($context['data']) : $data;
        $id = $this->normalizeIdentifier($context['id'] ?? $id) ?? $id;

        if ($data === []) {
            return false;
        }

        $ok = $this->builder()
            ->where($this->primaryKey, $id)
            ->set($data)
            ->update();

        $result = $ok && $this->db->affected_rows() >= 0;
        $this->trigger('afterUpdate', [
            'id' => $id,
            'data' => $data,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function insertBatch(array $rows): bool
    {
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[] = $this->prepareInsertData($row);
        }

        $context = $this->trigger('beforeInsertBatch', ['data' => $prepared]);
        $prepared = is_array($context['data'] ?? null) ? $context['data'] : $prepared;

        if ($prepared === []) {
            return false;
        }

        $preparedRows = $this->normalizeBatchRows($prepared);
        if ($preparedRows === []) {
            return false;
        }

        $result = $this->builder()->batchInsert($preparedRows);

        $this->trigger('afterInsertBatch', [
            'data' => $preparedRows,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function updateBatch(array $rows, ?string $index = null): bool
    {
        $resolvedIndex = $index !== null && trim($index) !== '' ? trim($index) : $this->primaryKey;
        $prepared = [];

        foreach ($rows as $row) {
            $prepared[] = $this->prepareUpdateData($row);
        }

        $context = $this->trigger('beforeUpdateBatch', [
            'index' => $resolvedIndex,
            'data' => $prepared,
        ]);
        $prepared = is_array($context['data'] ?? null) ? $context['data'] : $prepared;
        $contextIndex = $context['index'] ?? null;
        if (is_string($contextIndex) && trim($contextIndex) !== '') {
            $resolvedIndex = trim($contextIndex);
        }

        if ($prepared === []) {
            return false;
        }

        $preparedRows = $this->normalizeBatchRows($prepared);
        if ($preparedRows === []) {
            return false;
        }

        $result = $this->builder()->batchUpdate($preparedRows, $resolvedIndex);

        $this->trigger('afterUpdateBatch', [
            'index' => $resolvedIndex,
            'data' => $preparedRows,
            'result' => $result,
        ]);

        return $result;
    }

    public function delete(int|string $id): bool
    {
        $context = $this->trigger('beforeDelete', ['id' => $id]);
        $id = $context['id'] ?? $id;

        if ($this->useSoftDeletes) {
            $payload = $this->prepareDeleteData();
            $ok = $this->builder()
                ->where($this->primaryKey, $id)
                ->set($payload)
                ->update();
        } else {
            $ok = $this->builder()
                ->where($this->primaryKey, $id)
                ->delete();
        }

        $result = $ok && $this->db->affected_rows() >= 0;
        $this->trigger('afterDelete', [
            'id' => $id,
            'result' => $result,
        ]);

        return $result;
    }

    public function forceDelete(int|string $id): bool
    {
        $context = $this->trigger('beforeDelete', ['id' => $id, 'force' => true]);
        $id = $context['id'] ?? $id;

        $ok = $this->withDeleted()->builder()
            ->where($this->primaryKey, $id)
            ->delete();

        $result = $ok && $this->db->affected_rows() >= 0;
        $this->trigger('afterDelete', [
            'id' => $id,
            'force' => true,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * @param array<string, mixed> $where
     */
    public function deleteWhere(array $where): bool
    {
        $context = $this->trigger('beforeDelete', ['where' => $where]);
        $where = is_array($context['where'] ?? null) ? $this->normalizeAssoc($context['where']) : $where;

        if ($where === []) {
            return false;
        }

        if ($this->useSoftDeletes) {
            $payload = $this->prepareDeleteData();
            $ok = $this->builder()
                ->where($where)
                ->set($payload)
                ->update();
        } else {
            $ok = $this->builder()
                ->where($where)
                ->delete();
        }

        $result = $ok && $this->db->affected_rows() >= 0;
        $this->trigger('afterDelete', [
            'where' => $where,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * @param list<int|string> $ids
     * @return list<array<string, mixed>>
     */
    public function findMany(array $ids): array
    {
        $values = array_values(array_filter(
            $ids,
            static fn(mixed $id): bool => $id !== '',
        ));

        if ($values === []) {
            return [];
        }

        return $this->builder()
            ->whereIn($this->primaryKey, $values)
            ->getArray();
    }

    public function increment(int|string $id, string $column, int|float $amount = 1): bool
    {
        $ok = $this->builder()
            ->where($this->primaryKey, $id)
            ->increment($column, $amount);

        if (!$ok) {
            return false;
        }

        $touch = $this->prepareUpdateData([]);
        if ($touch === []) {
            return true;
        }

        $updated = $this->builder()
            ->where($this->primaryKey, $id)
            ->set($touch)
            ->update();

        return $updated && $this->db->affected_rows() >= 0;
    }

    public function decrement(int|string $id, string $column, int|float $amount = 1): bool
    {
        $ok = $this->builder()
            ->where($this->primaryKey, $id)
            ->decrement($column, $amount);

        if (!$ok) {
            return false;
        }

        $touch = $this->prepareUpdateData([]);
        if ($touch === []) {
            return true;
        }

        $updated = $this->builder()
            ->where($this->primaryKey, $id)
            ->set($touch)
            ->update();

        return $updated && $this->db->affected_rows() >= 0;
    }

    public function restore(int|string $id): bool
    {
        if (!$this->useSoftDeletes) {
            return false;
        }

        $payload = [$this->deletedAt => null];
        if ($this->useTimestamps) {
            $payload[$this->updatedAt] = $this->now();
        }
        if ($this->useUserTracking) {
            $actor = $this->resolveActorId();
            if ($actor !== null) {
                $payload[$this->updatedBy] = $actor;
            }
        }

        $ok = $this->withDeleted()->builder()
            ->where($this->primaryKey, $id)
            ->set($payload)
            ->update();

        return $ok && $this->db->affected_rows() >= 0;
    }

    /**
     * @param array<string, mixed> $where
     */
    public function restoreWhere(array $where): bool
    {
        if (!$this->useSoftDeletes || $where === []) {
            return false;
        }

        $payload = [$this->deletedAt => null];
        if ($this->useTimestamps) {
            $payload[$this->updatedAt] = $this->now();
        }
        if ($this->useUserTracking) {
            $actor = $this->resolveActorId();
            if ($actor !== null) {
                $payload[$this->updatedBy] = $actor;
            }
        }

        $ok = $this->withDeleted()->builder()
            ->where($where)
            ->set($payload)
            ->update();

        return $ok && $this->db->affected_rows() >= 0;
    }

    public function withDeleted(bool $state = true): self
    {
        $this->tempWithDeleted = $state;
        if ($state) {
            $this->tempOnlyDeleted = false;
        }

        return $this;
    }

    public function onlyDeleted(bool $state = true): self
    {
        $this->tempOnlyDeleted = $state;
        if ($state) {
            $this->tempWithDeleted = false;
        }

        return $this;
    }

    public function builder(): QueryBuilder
    {
        $builder = QueryBuilder::make($this->db)->table($this->table);
        $builder = $this->applySoftDeleteScope($builder);
        $this->resetSoftDeleteScope();

        return $builder;
    }

    public function query(): QueryBuilder
    {
        return $this->builder();
    }

    public function scoped(string $scope, mixed ...$args): QueryBuilder
    {
        return $this->applyScope($this->builder(), $scope, ...$args);
    }

    public function applyScope(QueryBuilder $builder, string $scope, mixed ...$args): QueryBuilder
    {
        $method = $this->scopeMethodName($scope);
        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException(sprintf(
                'Scope "%s" is not defined on %s.',
                $scope,
                static::class,
            ));
        }

        $result = $this->invokeCallbackMethod($method, $builder, ...$args);

        return $result instanceof QueryBuilder ? $result : $builder;
    }

    /**
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(): \Generator
    {
        yield from $this->builder()->cursor();
    }

    public function chunk(int $size, callable $callback): void
    {
        $size = max(1, $size);
        $page = 1;

        while (true) {
            $rows = $this->builder()
                ->limit($size, ($page - 1) * $size)
                ->getArray();

            if ($rows === []) {
                return;
            }

            $result = $callback($rows, $page);
            if ($result === false) {
                return;
            }

            if (count($rows) < $size) {
                return;
            }

            $page++;
        }
    }

    protected function tableName(): string
    {
        return $this->db->protect_identifiers($this->table, true, null, false);
    }

    protected function columnName(string $column): string
    {
        return $this->db->protect_identifiers($column, true, null, false);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareInsertData(array $data): array
    {
        $data = $this->filterAllowedFields($data);
        $now = $this->now();

        if ($this->useTimestamps) {
            if (!array_key_exists($this->createdAt, $data)) {
                $data[$this->createdAt] = $now;
            }

            if (!array_key_exists($this->updatedAt, $data)) {
                $data[$this->updatedAt] = $now;
            }
        }

        if ($this->useUserTracking) {
            $actor = $this->resolveActorId();

            if ($actor !== null && !array_key_exists($this->createdBy, $data)) {
                $data[$this->createdBy] = $actor;
            }

            if ($actor !== null && !array_key_exists($this->updatedBy, $data)) {
                $data[$this->updatedBy] = $actor;
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareUpdateData(array $data): array
    {
        $data = $this->filterAllowedFields($data);

        if ($this->useTimestamps && !array_key_exists($this->updatedAt, $data)) {
            $data[$this->updatedAt] = $this->now();
        }

        if ($this->useUserTracking) {
            $actor = $this->resolveActorId();
            if ($actor !== null && !array_key_exists($this->updatedBy, $data)) {
                $data[$this->updatedBy] = $actor;
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filterAllowedFields(array $data): array
    {
        if ($this->allowedFields === []) {
            return $data;
        }

        $allowed = $this->allowedFields;
        if ($this->useTimestamps) {
            $allowed[] = $this->createdAt;
            $allowed[] = $this->updatedAt;
        }
        if ($this->useUserTracking) {
            $allowed[] = $this->createdBy;
            $allowed[] = $this->updatedBy;
        }
        if ($this->useSoftDeletes) {
            $allowed[] = $this->deletedAt;
        }

        return array_intersect_key(
            $data,
            array_flip($allowed),
        );
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function setActorId(int|string|null $id): self
    {
        $this->actorId = $id;

        return $this;
    }

    protected function resolveActorId(): int|string|null
    {
        return $this->actorId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareDeleteData(): array
    {
        $payload = [
            $this->deletedAt => $this->now(),
        ];

        if ($this->useTimestamps) {
            $payload[$this->updatedAt] = $this->now();
        }

        if ($this->useUserTracking) {
            $actor = $this->resolveActorId();
            if ($actor !== null) {
                $payload[$this->updatedBy] = $actor;
            }
        }

        return $this->filterAllowedFields($payload);
    }

    protected function applySoftDeleteScope(QueryBuilder $builder): QueryBuilder
    {
        if (!$this->useSoftDeletes) {
            return $builder;
        }

        if ($this->tempWithDeleted) {
            return $builder;
        }

        if ($this->tempOnlyDeleted) {
            return $builder->where($this->deletedAt . ' !=', null);
        }

        return $builder->where($this->deletedAt, null);
    }

    protected function resetSoftDeleteScope(): void
    {
        $this->tempWithDeleted = false;
        $this->tempOnlyDeleted = false;
    }

    protected function scopeMethodName(string $scope): string
    {
        $normalized = trim($scope);
        if ($normalized === '') {
            return 'scope';
        }

        $normalized = str_replace(['-', '_'], ' ', $normalized);
        $normalized = str_replace(' ', '', ucwords($normalized));

        return 'scope' . $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function trigger(string $event, array $payload): array
    {
        if (!$this->allowCallbacks) {
            return $payload;
        }

        foreach ($this->eventCallbacks($event) as $callback) {
            if ($callback === '' || !method_exists($this, $callback)) {
                continue;
            }

            $result = $this->invokeCallbackMethod($callback, $payload);
            if (is_array($result)) {
                $payload = $result;
            }
        }

        return $this->normalizeAssoc($payload);
    }

    private function invokeCallbackMethod(string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($this, $method);

        return $reflection->invoke($this, ...$args);
    }

    /**
     * @return list<string>
     */
    private function eventCallbacks(string $event): array
    {
        return match ($event) {
            'beforeInsert' => $this->beforeInsert,
            'afterInsert' => $this->afterInsert,
            'beforeUpdate' => $this->beforeUpdate,
            'afterUpdate' => $this->afterUpdate,
            'beforeInsertBatch' => $this->beforeInsertBatch,
            'afterInsertBatch' => $this->afterInsertBatch,
            'beforeUpdateBatch' => $this->beforeUpdateBatch,
            'afterUpdateBatch' => $this->afterUpdateBatch,
            'beforeFind' => $this->beforeFind,
            'afterFind' => $this->afterFind,
            'beforeDelete' => $this->beforeDelete,
            'afterDelete' => $this->afterDelete,
            default => [],
        };
    }

    /**
     * @param list<array<string, mixed>> $fallback
     * @return list<array<string, mixed>>
     */
    private function normalizeRowList(mixed $value, array $fallback): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return $fallback;
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                return $fallback;
            }

            $rows[] = $this->normalizeAssoc($row);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeBatchRows(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                return [];
            }

            $rows[] = $this->normalizeAssoc($row);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeAssocRow(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        return $this->normalizeAssoc($row);
    }

    /**
     */
    private function normalizeIdentifier(mixed $value): int|string|null
    {
        if (is_int($value) || is_string($value)) {
            return $value === '' ? null : $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
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
}
