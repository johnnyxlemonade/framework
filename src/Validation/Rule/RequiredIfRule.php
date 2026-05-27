<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class RequiredIfRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        if ($param === null || trim($param) === '') {
            return false;
        }

        [$otherField, $expected] = array_pad(explode(',', $param, 2), 2, '');
        $otherField = trim($otherField);
        $expected = trim($expected);

        if ($otherField === '') {
            return false;
        }

        $otherValue = $data[$otherField] ?? null;
        $other = is_scalar($otherValue) ? (string) $otherValue : '';
        if ($other !== $expected) {
            return true;
        }

        return !($value === null || (is_string($value) && trim($value) === ''));
    }
}
