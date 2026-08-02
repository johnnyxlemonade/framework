<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Endpoint;

final class DefaultVatValidationEndpointProvider implements VatValidationEndpointProviderInterface
{
    public function validationUrl(string $countryCode, string $vatNumber): string
    {
        unset($countryCode, $vatNumber);

        throw new ValidationEndpointNotConfiguredException(
            'VAT validation endpoint is not configured.',
        );
    }
}
