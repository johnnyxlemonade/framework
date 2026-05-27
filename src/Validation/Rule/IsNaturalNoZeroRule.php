<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;

final class IsNaturalNoZeroRule implements ValidationRuleInterface
{
    use RegexValidationTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if (!is_scalar($value)) {
            return false;
        }

        return (string) $value !== '0' && $this->matchesScalar($value, '/^[0-9]+$/');
    }
}
