<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidPasswordRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        if (!is_scalar($value)) {
            return false;
        }

        $password = trim((string) $value);
        $special = in_array(strtolower((string) $param), ['1', 'true', 'yes', 'special'], true);

        $minLength = 6;
        $maxLength = 20;

        if ($password === '') {
            return false;
        }

        if (mb_strlen($password) < $minLength || mb_strlen($password) > $maxLength) {
            return false;
        }

        if (preg_match('/[a-z]/', $password) !== 1) {
            return false;
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            return false;
        }

        if (preg_match('/[0-9]/', $password) !== 1) {
            return false;
        }

        if ($special && preg_match('/[!@#$%^&*()\-_=+{};:,<.>Ä‚â€šĂ‚Â§~]/', $password) !== 1) {
            return false;
        }

        return preg_match('/(.)\1{2,}/', $password) !== 1;
    }
}
