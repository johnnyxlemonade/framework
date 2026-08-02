<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Endpoint\DefaultValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;
use PHPUnit\Framework\TestCase;

final class DefaultValidationEndpointProviderTest extends TestCase
{
    public function testEmailValidationUrlFailsClosedWhenNotConfigured(): void
    {
        $provider = new DefaultValidationEndpointProvider();

        $this->expectException(ValidationEndpointNotConfiguredException::class);
        $this->expectExceptionMessage('Email validation endpoint is not configured.');

        $provider->emailValidationUrl('test@example.com');
    }

    public function testCompanyValidationUrlFailsClosedWhenNotConfigured(): void
    {
        $provider = new DefaultValidationEndpointProvider();

        $this->expectException(ValidationEndpointNotConfiguredException::class);
        $this->expectExceptionMessage('Company validation endpoint is not configured.');

        $provider->activeCompanyValidationUrl('12345678');
    }
}
