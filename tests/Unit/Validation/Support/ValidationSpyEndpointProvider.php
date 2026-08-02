<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;

final class ValidationSpyEndpointProvider implements ValidationEndpointProviderInterface
{
    public ?string $lastEmail = null;
    public ?string $lastIco = null;

    public function __construct(
        private readonly string $emailUrl,
        private readonly string $companyUrl,
    ) {}

    public function emailValidationUrl(string $email): string
    {
        $this->lastEmail = $email;

        return $this->emailUrl;
    }

    public function activeCompanyValidationUrl(string $ico): string
    {
        $this->lastIco = $ico;

        return $this->companyUrl;
    }
}
