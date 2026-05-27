<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class InListRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);
        if (!is_scalar($value)) {
            return false;
        }

        return in_array((string) $value, array_map('trim', explode(',', (string) $param)), true);
    }
}
