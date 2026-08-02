<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;

final class VatStaticEndpointProvider implements VatValidationEndpointProviderInterface
{
    public function __construct(
        private readonly string $url,
    ) {}

    public function validationUrl(string $countryCode, string $vatNumber): string
    {
        unset($countryCode, $vatNumber);

        return $this->url;
    }
}
