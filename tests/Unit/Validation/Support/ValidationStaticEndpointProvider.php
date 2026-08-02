<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;

final class ValidationStaticEndpointProvider implements ValidationEndpointProviderInterface
{
    public function __construct(
        private readonly string $emailUrl,
        private readonly string $companyUrl,
    ) {}

    public function emailValidationUrl(string $email): string
    {
        unset($email);

        return $this->emailUrl;
    }

    public function activeCompanyValidationUrl(string $ico): string
    {
        unset($ico);

        return $this->companyUrl;
    }
}
