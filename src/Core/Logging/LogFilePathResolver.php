<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Core\Context\ApplicationContext;

final class LogFilePathResolver
{
    public function __construct(
        private readonly ApplicationContext $context,
    ) {}

    public function resolve(string $path, string $fallback): string
    {
        $path = trim($path);

        if ($path === '') {
            return $fallback;
        }

        return $this->context->resolveLogPath($path);
    }
}
