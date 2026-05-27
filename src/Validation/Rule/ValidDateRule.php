<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidDateRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if (!is_scalar($value)) {
            return false;
        }

        return strtotime((string) $value) !== false;
    }
}
