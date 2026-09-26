<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

final readonly class ApiSecurityConfig
{
    public function __construct(
        public ?StaticBearerConfig $staticBearer,
    ) {}
}
