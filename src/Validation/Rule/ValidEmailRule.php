<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidEmailRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if (!is_string($value)) {
            return false;
        }

        $email = $value;
        if (function_exists('idn_to_ascii') && preg_match('#\A([^@]+)@(.+)\z#', $email, $matches) === 1) {
            $domain = defined('INTL_IDNA_VARIANT_UTS46')
                ? idn_to_ascii($matches[2], 0, INTL_IDNA_VARIANT_UTS46)
                : idn_to_ascii($matches[2]);

            if ($domain !== false) {
                $email = $matches[1] . '@' . $domain;
            }
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
