<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;

final class ValidIcoActiveRule implements ValidationRuleInterface
{
    use JsonFetchTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value)) {
            return false;
        }

        $ico = preg_replace('/\s+/', '', $value) ?? '';

        if ($ico === '' || preg_match('/^\d{8}$/', $ico) !== 1) {
            return false;
        }

        $url = sprintf(
            'https://api.core1.agency/validator/company?value=%s',
            rawurlencode($ico),
        );

        $json = $this->fetchJson($url);

        if ($json === null) {
            return false;
        }

        return ($json['valid'] ?? false) === true;
    }
}
