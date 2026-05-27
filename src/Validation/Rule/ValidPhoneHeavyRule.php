<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;
use Lemonade\Framework\Validation\Rule\Traits\RuleValueHelperTrait;

final class ValidPhoneHeavyRule implements ValidationRuleInterface
{
    use RegexValidationTrait;
    use RuleValueHelperTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if (!is_scalar($value)) {
            return false;
        }

        $phone = (string) $value;
        if (!$this->matchesString($value, '/^[0-9][0-9\s\-]{4,14}$/')) {
            return false;
        }

        foreach ($this->getPhoneDialingCode() as $code) {
            $formattedCodes = $this->formatDialingCode((string) $code);
            if (array_filter($formattedCodes, static fn(string $dial): bool => str_starts_with($phone, $dial)) !== []) {
                return false;
            }
        }

        return true;
    }
}
