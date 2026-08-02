<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Endpoint\DefaultVatValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;
use PHPUnit\Framework\TestCase;

final class DefaultVatValidationEndpointProviderTest extends TestCase
{
    public function testValidationUrlFailsClosedWhenNotConfigured(): void
    {
        $provider = new DefaultVatValidationEndpointProvider();

        $this->expectException(ValidationEndpointNotConfiguredException::class);
        $this->expectExceptionMessage('VAT validation endpoint is not configured.');

        $provider->validationUrl('CZ', '12345678');
    }
}
