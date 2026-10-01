<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Core\Context\ApplicationContext;

/**
 * Resolves built-in logging channels to the framework-owned writable log directory.
 */
final class LogFilePathResolver
{
    /**
     * Binds canonical log paths to the active application context.
     */
    public function __construct(
        private readonly ApplicationContext $context,
    ) {
    }

    /**
     * Returns the canonical base filename for a built-in logging channel.
     */
    public function resolve(string $channel): string
    {
        return $this->context->resolveLogPath($channel . '.log');
    }
}
