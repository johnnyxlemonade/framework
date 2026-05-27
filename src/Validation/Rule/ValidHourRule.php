<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidHourRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        $hour = is_string($value) ? $value : null;
        $type = $param ?? '24H';

        if ($hour === '' || $hour === null) {
            return false;
        }

        $hasSec = mb_substr_count($hour, ':') >= 2;
        $pattern = '/^'
            . (($type === '24H') ? '([1-2][0-3]|[01]?[1-9])' : '(1[0-2]|0?[1-9])')
            . ':([0-5]?[0-9])'
            . ($hasSec ? ':([0-5]?[0-9])' : '')
            . (($type === '24H') ? '' : '( AM| PM| am| pm)')
            . '$/';

        return preg_match($pattern, $hour) === 1;
    }
}
