<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class GreaterThanEqualToRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);
        return is_numeric($value) && is_numeric($param) && (float) $value >= (float) $param;
    }
}
