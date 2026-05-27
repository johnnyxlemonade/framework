<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class ValidStreetAddressRule implements ValidationRuleInterface
{
    private const STREET_ADDRESS_REGEX = "/^[\p{L}0-9\s.\-]{1,64}\s+\d+[a-zA-Z]?(?:\/\d+)?$/u";
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        return is_string($value) && preg_match(self::STREET_ADDRESS_REGEX, $value) === 1;
    }
}
