<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;
use Lemonade\Framework\Validation\Rule\Traits\RuleValueHelperTrait;

final class ValidPostcodeRule implements ValidationRuleInterface
{
    use RegexValidationTrait;
    use RuleValueHelperTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        $postCode = is_string($value) ? $value : null;
        $countryField = $param ?? '';
        $countryCodeRaw = $data[$countryField] ?? ($_GET[$countryField] ?? $_POST[$countryField] ?? 'CZ');
        $countryCode = is_scalar($countryCodeRaw) ? (string) $countryCodeRaw : 'CZ';

        if ($this->isEmptyString($postCode) || $this->isEmptyString($countryCode)) {
            return false;
        }

        if (
            class_exists('Lemonade\\Postcode\\PostcodeFormatter')
            && class_exists('Lemonade\\Postcode\\FormatterRegistry')
            && class_exists('Lemonade\\Postcode\\CountryCode')
            && class_exists('Lemonade\\Postcode\\FormatterMapper')
        ) {
            try {
                $registry = new \Lemonade\Postcode\FormatterRegistry(\Lemonade\Postcode\FormatterMapper::all());
                $formatter = new \Lemonade\Postcode\PostcodeFormatter($registry);

                return (bool) $formatter->format(\Lemonade\Postcode\CountryCode::tryFrom($countryCode), $postCode);
            } catch (\Throwable) {
                return $this->matchesString((string) $postCode, '/^[a-zA-Z0-9\- ]{4,16}$/');
            }
        }

        return $this->matchesString((string) $postCode, '/^[a-zA-Z0-9\- ]{4,16}$/');
    }
}
