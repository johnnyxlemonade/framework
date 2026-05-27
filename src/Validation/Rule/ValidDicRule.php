<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidDicRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        $dic = is_string($value) ? $value : '';
        if ($dic === '') {
            return true;
        }

        // @link http://ec.europa.eu/taxation_customs/vies/faq.html?locale=cs#item_11
        $patterns = [
            'AT' => 'U[A-Z\d]{8}',
            'BE' => '(0\d{9}|\d{10})',
            'BG' => '\d{9,10}',
            'CY' => '\d{8}[A-Z]',
            'CZ' => '\d{8,10}',
            'DE' => '\d{9}',
            'DK' => '(\d{2} ?){3}\d{2}',
            'EE' => '\d{9}',
            'EL' => '\d{9}',
            'ES' => '[A-Z]\d{7}[A-Z]|\d{8}[A-Z]|[A-Z]\d{8}',
            'FI' => '\d{8}',
            'FR' => '([A-Z]{2}|\d{2})\d{9}',
            'GB' => '\d{9}|\d{12}|(GD|HA)\d{3}',
            'HR' => '\d{11}',
            'HU' => '\d{8}',
            'IE' => '[A-Z\d]{8}|[A-Z\d]{9}',
            'IT' => '\d{11}',
            'LT' => '(\d{9}|\d{12})',
            'LU' => '\d{8}',
            'LV' => '\d{11}',
            'MT' => '\d{8}',
            'NL' => '\d{9}B\d{2}',
            'PL' => '\d{10}',
            'PT' => '\d{9}',
            'RO' => '\d{2,10}',
            'SE' => '\d{12}',
            'SI' => '\d{8}',
            'SK' => '\d{10}',
        ];

        $dic = strtoupper($dic);
        $country = substr($dic, 0, 2);
        $number = substr($dic, 2);

        if (!isset($patterns[$country])) {
            return false;
        }

        return preg_match('/^' . $patterns[$country] . '$/', $number) === 1;
    }
}
