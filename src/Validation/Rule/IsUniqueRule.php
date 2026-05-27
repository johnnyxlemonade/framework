<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\DatabaseRuleTrait;

final class IsUniqueRule implements ValidationRuleInterface
{
    use DatabaseRuleTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        if (!is_string($value) || $param === null || trim($param) === '') {
            return false;
        }

        if (sscanf($param, '%[^.].%[^.]', $table, $field) !== 2) {
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
            'SELECT COUNT(*) AS c FROM `%s` WHERE `%s` = :value LIMIT 1',
            $table,
            $field,
        );

        $rows = $db->select($sql, ['value' => $value]);
        $countRaw = $rows[0]['c'] ?? 0;
        $count = is_int($countRaw) ? $countRaw : (is_numeric($countRaw) ? (int) $countRaw : 0);

        return $count === 0;
    }
}
