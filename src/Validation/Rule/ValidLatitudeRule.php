<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;

final class ValidLatitudeRule implements ValidationRuleInterface
{
    use RegexValidationTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        return $this->matchesScalar($value, '/^(\+|-)?(?:90(?:(?:\.0{1,6})?)|(?:[0-9]|[1-8][0-9])(?:(?:\.[0-9]{1,6})?))$/');
    }
}
