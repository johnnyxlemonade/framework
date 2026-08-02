<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;

final class RecaptchaThrowingEndpointProvider implements RecaptchaEndpointProviderInterface
{
    public function verificationUrl(): string
    {
        throw new ValidationEndpointNotConfiguredException(
            'reCAPTCHA verification endpoint is not configured.',
        );
    }
}
