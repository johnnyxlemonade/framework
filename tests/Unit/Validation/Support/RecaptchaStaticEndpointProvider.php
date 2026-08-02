<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;

final class RecaptchaStaticEndpointProvider implements RecaptchaEndpointProviderInterface
{
    public function __construct(
        private readonly string $url,
    ) {}

    public function verificationUrl(): string
    {
        return $this->url;
    }
}
