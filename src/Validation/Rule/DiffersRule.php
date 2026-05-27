<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class DiffersRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        $left = is_scalar($value) ? (string) $value : '';
        $rightValue = $data[$param ?? ''] ?? null;
        $right = is_scalar($rightValue) ? (string) $rightValue : '';

        return $left !== $right;
    }
}
