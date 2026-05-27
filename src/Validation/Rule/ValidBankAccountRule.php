<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidBankAccountRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        $bankAccount = is_scalar($value) ? (string) $value : '';

        if (class_exists('Lemonade\\Pdf\\Components\\Bank')) {
            try {
                return (new \Lemonade\Pdf\Components\Bank($bankAccount))->isValid() === true;
            } catch (\Exception) {
                return false;
            }
        }

        return preg_match('/(([\d]{0,6})[\-])?([\d]{2,10})\/([\d]{4})/', $bankAccount) === 1;
    }
}
