<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

final class StaticBearerConfig
{
    /**
     * @param list<non-empty-string> $scopes
     */
    public function __construct(
        public readonly string $token,
        public readonly array $scopes,
    ) {}
}
