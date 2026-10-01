<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

/**
 * Holds the small application policy surface for built-in framework logging.
 */
final readonly class LoggingConfig
{
    /**
     * Initializes shared retention and optional request and benchmark logging policies.
     */
    public function __construct(
        public int $retentionDays,
        public bool $requestEnabled,
        public int $requestMinStatus,
        public bool $benchmarkEnabled,
    ) {
    }
}
