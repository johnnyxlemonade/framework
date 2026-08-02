<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use RuntimeException;

final class ValidationThrowingEndpointProvider implements ValidationEndpointProviderInterface
{
    public function emailValidationUrl(string $email): string
    {
        unset($email);

        throw new RuntimeException('Missing endpoint');
    }

    public function activeCompanyValidationUrl(string $ico): string
    {
        unset($ico);

        throw new RuntimeException('Missing endpoint');
    }
}
