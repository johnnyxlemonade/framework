<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class CorsConfig
{
    /**
     * @param list<non-empty-string> $allowedOrigins
     * @param list<non-empty-string> $allowedMethods
     * @param list<non-empty-string> $allowedHeaders
     * @param list<non-empty-string> $exposedHeaders
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $allowedOrigins,
        public readonly array $allowedMethods,
        public readonly array $allowedHeaders,
        public readonly array $exposedHeaders,
        public readonly bool $allowCredentials,
        public readonly ?int $maxAge,
    ) {}
}
