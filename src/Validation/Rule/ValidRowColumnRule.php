<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\DatabaseRuleTrait;

final class ValidRowColumnRule implements ValidationRuleInterface
{
    use DatabaseRuleTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        $rowId = is_scalar($value) ? (string) $value : '';
        $rule = $param ?? '';

        if ($rowId === '' || $rule === '') {
            return false;
        }

        if (sscanf($rule, '%[^.].%[^.]', $table, $field) !== 2) {
            return false;
        }
        if (!is_string($table) || !is_string($field)) {
            return false;
        }

        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($field)) {
            return false;
        }

        $db = $this->database();
        if ($db === null) {
            return false;
        }

        $sql = sprintf(
            'SELECT `%s` AS checkRowId FROM `%s` WHERE `%s` = :value LIMIT 1',
            $field,
            $table,
            $field,
        );

        $rows = $db->select($sql, ['value' => $rowId]);
        if ($rows === []) {
            return false;
        }

        $checkRowId = $rows[0]['checkRowId'] ?? null;

        return (is_scalar($checkRowId) ? (string) $checkRowId : '') === $rowId;
    }
}
