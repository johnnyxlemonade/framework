<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\DatabaseRuleTrait;

final class IsUniqueExceptRule implements ValidationRuleInterface
{
    use DatabaseRuleTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        if (!is_string($value) || $param === null || trim($param) === '') {
            return false;
        }

        if (sscanf($param, '%[^.].%[^.].%[^.]', $table, $field, $idField) !== 3) {
            return false;
        }
        if (!is_string($table) || !is_string($field) || !is_string($idField)) {
            return false;
        }

        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($field) || !$this->isSafeIdentifier($idField)) {
            return false;
        }

        $db = $this->database();
        if ($db === null) {
            return false;
        }

        $idValue = $data[$idField] ?? ($_POST[$idField] ?? null);

        $sql = sprintf(
            'SELECT COUNT(*) AS c FROM `%s` WHERE `%s` = :value',
            $table,
            $field,
        );
        $bindings = ['value' => $value];

        if ($idValue !== null && $idValue !== '') {
            $sql .= sprintf(' AND `%s` != :id_value', $idField);
            $bindings['id_value'] = $idValue;
        }

        $sql .= ' LIMIT 1';

        $rows = $db->select($sql, $bindings);
        $countRaw = $rows[0]['c'] ?? 0;
        $count = is_int($countRaw) ? $countRaw : (is_numeric($countRaw) ? (int) $countRaw : 0);

        return $count === 0;
    }
}
