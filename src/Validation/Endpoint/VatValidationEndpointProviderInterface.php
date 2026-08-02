<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Endpoint;

interface VatValidationEndpointProviderInterface
{
    public function validationUrl(string $countryCode, string $vatNumber): string;
}
