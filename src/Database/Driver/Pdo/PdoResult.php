<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Database\Result\ArrayDatabaseResult;

final class PdoResult extends ArrayDatabaseResult
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(array $rows)
    {
        parent::__construct($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self($rows);
    }

    public function num_fields(): int
    {
        if (!isset($this->rows[0])) {
            return 0;
        }

        return count($this->rows[0]);
    }

    /**
     * @return list<string>
     */
    public function list_fields(): array
    {
        if (!isset($this->rows[0])) {
            return [];
        }

        return array_keys($this->rows[0]);
    }

    /**
     * @return list<object>
     */
    public function field_data(): array
    {
        $fields = [];

        foreach ($this->list_fields() as $fieldName) {
            $fields[] = (object) [
                'name' => $fieldName,
                'type' => 'mixed',
                'max_length' => 0,
                'primary_key' => false,
                'default' => null,
            ];
        }

        return $fields;
    }

}
