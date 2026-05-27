<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidTwoDatesRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        $dateInput = is_string($value) ? $value : null;
        if ($dateInput === '' || $dateInput === null) {
            return false;
        }

        $timestamp = strtotime($dateInput);
        if ($timestamp === false) {
            return false;
        }

        $rule = $param ?? '';
        $parts = explode('#', $rule);
        if (count($parts) < 2) {
            return false;
        }

        $from = strtotime($parts[0]);
        $to = strtotime($parts[1]);
        if ($from === false || $to === false) {
            return false;
        }

        return !($timestamp < $from || $timestamp > $to);
    }
}
