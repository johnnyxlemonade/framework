<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidDecimalNaturalRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        return is_scalar($value) && preg_match('/^\d+(?:[.,]\d+)?$/', (string) $value) === 1;
    }
}
