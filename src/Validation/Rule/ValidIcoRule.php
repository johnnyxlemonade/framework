<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;

final class ValidIcoRule implements ValidationRuleInterface
{
    use RegexValidationTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if (!is_scalar($value)) {
            return false;
        }

        $ico = preg_replace('#\s+#', '', (string) $value) ?? '';
        if (!$this->matchesString($ico, '/^\d{8}$/')) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $ico[$i] * (8 - $i);
        }
        $mod = $sum % 11;
        $check = 11 - $mod;
        if ($check === 10) {
            $check = 0;
        } elseif ($check === 11) {
            $check = 1;
        }

        return $check === (int) $ico[7];
    }
}
