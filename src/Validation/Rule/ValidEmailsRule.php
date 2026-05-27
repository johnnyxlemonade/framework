<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidEmailsRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if (!is_string($value)) {
            return false;
        }

        $validator = new ValidEmailRule();
        if (strpos($value, ',') === false) {
            return $validator->validate(trim($value), null, []);
        }

        foreach (explode(',', $value) as $email) {
            $email = trim($email);
            if ($email !== '' && !$validator->validate($email, null, [])) {
                return false;
            }
        }

        return true;
    }
}
