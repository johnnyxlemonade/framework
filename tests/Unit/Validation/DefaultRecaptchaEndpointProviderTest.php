<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Endpoint\DefaultRecaptchaEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;
use PHPUnit\Framework\TestCase;

final class DefaultRecaptchaEndpointProviderTest extends TestCase
{
    public function testVerificationUrlFailsClosedWhenNotConfigured(): void
    {
        $provider = new DefaultRecaptchaEndpointProvider();

        $this->expectException(ValidationEndpointNotConfiguredException::class);
        $this->expectExceptionMessage('reCAPTCHA verification endpoint is not configured.');

        $provider->verificationUrl();
    }
}
