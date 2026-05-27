<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;

final class ValidDicActiveRule implements ValidationRuleInterface
{
    use JsonFetchTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value)) {
            return false;
        }

        $vatId = strtoupper((string) preg_replace('/\s+/', '', $value));

        if (strlen($vatId) < 4) {
            return false;
        }

        $country = substr($vatId, 0, 2);
        $number = substr($vatId, 2);

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1 || $number === '') {
            return false;
        }

        $url = sprintf(
            'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s',
            rawurlencode($country),
            rawurlencode($number),
        );

        $json = $this->fetchJson($url);

        if ($json === null) {
            return false;
        }

        return ($json['isValid'] ?? false) === true;
    }
}
