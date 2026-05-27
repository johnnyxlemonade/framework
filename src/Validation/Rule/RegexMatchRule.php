<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class RegexMatchRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);
        return is_string($value) && $param !== null && preg_match($param, $value) === 1;
    }
}
