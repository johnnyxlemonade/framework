<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Endpoint;

final class DefaultRecaptchaEndpointProvider implements RecaptchaEndpointProviderInterface
{
    public function verificationUrl(): string
    {
        throw new ValidationEndpointNotConfiguredException(
            'reCAPTCHA verification endpoint is not configured.',
        );
    }
}
