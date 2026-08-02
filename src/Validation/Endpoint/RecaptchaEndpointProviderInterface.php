<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Endpoint;

interface RecaptchaEndpointProviderInterface
{
    public function verificationUrl(): string;
}
