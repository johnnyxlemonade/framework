<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;
use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;

final class VatThrowingEndpointProvider implements VatValidationEndpointProviderInterface
{
    public function validationUrl(string $countryCode, string $vatNumber): string
    {
        unset($countryCode, $vatNumber);

        throw new ValidationEndpointNotConfiguredException(
            'VAT validation endpoint is not configured.',
        );
    }
}
