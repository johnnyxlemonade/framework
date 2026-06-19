<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

final class ApiSecurityConfig
{
    public function __construct(
        public readonly ?StaticBearerConfig $staticBearer,
    ) {}
}
