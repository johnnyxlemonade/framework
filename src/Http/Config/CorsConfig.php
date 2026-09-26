<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final readonly class CorsConfig
{
    /**
     * @param list<non-empty-string> $allowedOrigins
     * @param list<non-empty-string> $allowedMethods
     * @param list<non-empty-string> $allowedHeaders
     * @param list<non-empty-string> $exposedHeaders
     */
    public function __construct(
        public bool $enabled,
        public array $allowedOrigins,
        public array $allowedMethods,
        public array $allowedHeaders,
        public array $exposedHeaders,
        public bool $allowCredentials,
        public ?int $maxAge,
    ) {}
}
