<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class RequiredRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        return !($value === null || (is_string($value) && trim($value) === ''));
    }
}
