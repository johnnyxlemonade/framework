<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Endpoint;

final class DefaultValidationEndpointProvider implements ValidationEndpointProviderInterface
{
    public function emailValidationUrl(string $email): string
    {
        unset($email);

        throw new ValidationEndpointNotConfiguredException(
            'Email validation endpoint is not configured.',
        );
    }

    public function activeCompanyValidationUrl(string $ico): string
    {
        unset($ico);

        throw new ValidationEndpointNotConfiguredException(
            'Company validation endpoint is not configured.',
        );
    }
}
