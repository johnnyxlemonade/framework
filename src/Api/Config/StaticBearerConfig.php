<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

final readonly class StaticBearerConfig
{
    /**
     * @param list<non-empty-string> $scopes
     */
    public function __construct(
        public string $token,
        public array $scopes,
    ) {}
}
