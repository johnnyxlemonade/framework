<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;

final class ValidBase64Rule implements ValidationRuleInterface
{
    use RegexValidationTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if (!is_string($value)) {
            return false;
        }

        if (!$this->matchesString($value, '/^[A-Za-z0-9+\/]*={0,2}$/')) {
            return false;
        }
        $decoded = base64_decode($value, true);

        return base64_encode($decoded !== false ? $decoded : '') === $value;
    }
}
