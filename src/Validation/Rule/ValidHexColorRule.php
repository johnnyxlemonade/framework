<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;

final class ValidHexColorRule implements ValidationRuleInterface
{
    use RegexValidationTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        return $this->matchesString($value, '/^#?(?:[0-9a-f]{3}|[0-9a-f]{6})$/i');
    }
}
