<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule\Traits;

trait RegexValidationTrait
{
    protected function matchesString(mixed $value, string $pattern): bool
    {
        return is_string($value) && preg_match($pattern, $value) === 1;
    }

    protected function matchesScalar(mixed $value, string $pattern): bool
    {
        return is_scalar($value) && preg_match($pattern, (string) $value) === 1;
    }
}
