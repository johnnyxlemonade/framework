<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Endpoint;

interface ValidationEndpointProviderInterface
{
    public function emailValidationUrl(string $email): string;

    public function activeCompanyValidationUrl(string $ico): string;
}
