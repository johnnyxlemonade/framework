<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidUrlRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
