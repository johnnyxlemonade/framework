<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;
use Lemonade\Framework\Validation\Rule\Traits\RuleValueHelperTrait;

final class ValidPhonenumberRule implements ValidationRuleInterface
{
    use RegexValidationTrait;
    use RuleValueHelperTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        $phoneNumber = is_string($value) ? $value : null;
        $countryField = $param ?? '';
        $countryCodeRaw = $data[$countryField] ?? ($_GET[$countryField] ?? $_POST[$countryField] ?? 'CZ');
        $countryCode = is_scalar($countryCodeRaw) ? (string) $countryCodeRaw : 'CZ';

        if ($this->isEmptyString($phoneNumber) || $this->isEmptyString($countryCode)) {
            return false;
        }

        if (class_exists('Lemonade\\PhoneNumber\\PhoneNumber')) {
            $valid = false;

            try {
                $number = \Lemonade\PhoneNumber\PhoneNumber::parse($phoneNumber, $countryCode);
                $valid = is_object($number)
                    && method_exists($number, 'isPossibleNumber')
                    && method_exists($number, 'isValidNumber')
                    && $number->isPossibleNumber()
                    && $number->isValidNumber();
            } catch (\Exception) {
                $valid = false;
            }

            if (!$valid) {
                return false;
            }

            if (!$this->matchesString((string) $phoneNumber, '/^[0-9][0-9\s\-]{5,15}$/')) {
                return false;
            }

            foreach ($this->getPhoneDialingCode() as $code) {
                $formattedCodes = $this->formatDialingCode((string) $code);

                if (array_filter($formattedCodes, static fn(string $dial): bool => str_starts_with((string) $phoneNumber, $dial)) !== []) {
                    return false;
                }
            }

            return true;
        }

        return $this->matchesString((string) $phoneNumber, '/^[0-9][0-9\s\-]{5,15}$/');
    }
}
