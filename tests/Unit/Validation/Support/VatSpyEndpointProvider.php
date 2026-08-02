<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;

final class VatSpyEndpointProvider implements VatValidationEndpointProviderInterface
{
    public ?string $lastCountryCode = null;
    public ?string $lastVatNumber = null;

    public function __construct(
        private readonly string $url,
    ) {}

    public function validationUrl(string $countryCode, string $vatNumber): string
    {
        $this->lastCountryCode = $countryCode;
        $this->lastVatNumber = $vatNumber;

        return $this->url;
    }
}
