<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;

final class RecaptchaSpyEndpointProvider implements RecaptchaEndpointProviderInterface
{
    public int $callCount = 0;

    public function __construct(
        private readonly string $url,
    ) {}

    public function verificationUrl(): string
    {
        $this->callCount++;

        return $this->url;
    }
}
