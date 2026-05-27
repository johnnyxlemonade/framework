<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Context;

enum Environment: string
{
    case Development = 'dev';
    case Production = 'prod';
    case Testing = 'test';

    public function isDebugDefault(): bool
    {
        return $this !== self::Production;
    }

    public function isDevelopment(): bool
    {
        return $this === self::Development;
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    public function isTesting(): bool
    {
        return $this === self::Testing;
    }

    public static function fromString(?string $value): self
    {
        return match (strtolower(trim((string) $value))) {
            'dev', 'development', 'local' => self::Development,
            'test', 'testing' => self::Testing,
            default => self::Production,
        };
    }
}
