<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidIpRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        if (!is_string($value)) {
            return false;
        }

        $which = strtolower($param ?? '');
        $flags = match ($which) {
            'ipv4', '4' => FILTER_FLAG_IPV4,
            'ipv6', '6' => FILTER_FLAG_IPV6,
            default => 0,
        };

        return filter_var($value, FILTER_VALIDATE_IP, $flags) !== false;
    }
}
