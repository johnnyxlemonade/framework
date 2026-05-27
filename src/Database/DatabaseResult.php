<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

abstract class DatabaseResult implements DatabaseResultInterface
{
    /** @var list<array<string, mixed>>|null */
    private ?array $resultArray = null;

    /** @var list<object>|null */
    private ?array $resultObject = null;

    /** @var array<string, list<object>> */
    private array $customResultObject = [];

    private int $currentRow = 0;

    /**
     * @return list<object>|list<array<string, mixed>>
     */
    public function result(string $type = 'object'): array
    {
        if ($type === 'array') {
            return $this->result_array();
        }

        if ($type === 'object') {
            return $this->result_object();
        }

        return $this->custom_result_object($type);
    }

    /**
     * @return list<object>
     */
    public function result_object(): array
    {
        if ($this->resultObject !== null) {
            return $this->resultObject;
        }

        $objects = [];

        foreach ($this->result_array() as $row) {
            $objects[] = (object) $row;
        }

        return $this->resultObject = $objects;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function result_array(): array
    {
        if ($this->resultArray !== null) {
            return $this->resultArray;
        }

        $this->data_seek(0);

        $rows = [];

        while (($row = $this->fetchAssoc()) !== null) {
            $rows[] = $row;
        }

        return $this->resultArray = $rows;
    }

    /**
     * @return list<object>
     */
    public function custom_result_object(string $className): array
    {
        if (isset($this->customResultObject[$className])) {
            return $this->customResultObject[$className];
        }

        $objects = [];

        foreach ($this->result_array() as $row) {
            $object = new $className();

            foreach ($row as $key => $value) {
                if (!property_exists($object, $key)) {
                    continue;
                }

                $property = new \ReflectionProperty($object, $key);
                $property->setAccessible(true);
                $property->setValue($object, $value);
            }

            $objects[] = $object;
        }

        return $this->customResultObject[$className] = $objects;
    }

    public function row(int|string $n = 0, string $type = 'object'): mixed
    {
        if (!is_numeric($n)) {
            $row = $this->row_array(0);

            return $row[$n] ?? null;
        }

        if ($type === 'array') {
            return $this->row_array((int) $n);
        }

        return $this->row_object((int) $n);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function row_array(int $n = 0): ?array
    {
        $rows = $this->result_array();

        return $rows[$n] ?? null;
    }

    public function row_object(int $n = 0): ?object
    {
        $rows = $this->result_object();

        return $rows[$n] ?? null;
    }

    public function first_row(string $type = 'object'): mixed
    {
        return $this->row(0, $type);
    }

    public function last_row(string $type = 'object'): mixed
    {
        $index = $this->num_rows() - 1;

        return $index >= 0 ? $this->row($index, $type) : null;
    }

    public function next_row(string $type = 'object'): mixed
    {
        $this->currentRow++;

        return $this->row($this->currentRow, $type);
    }

    public function previous_row(string $type = 'object'): mixed
    {
        $this->currentRow = max(0, $this->currentRow - 1);

        return $this->row($this->currentRow, $type);
    }

    /**
     * @return array<string, mixed>|null
     */
    abstract protected function fetchAssoc(): ?array;
}
