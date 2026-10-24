<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

/**
 * Carries the immutable application policy resolved for built-in framework logging.
 */
final readonly class LoggingConfig
{
    /**
     * Initializes shared retention and optional request and benchmark logging policies.
     */
    public function __construct(
        private int $retentionDays,
        private bool $requestEnabled,
        private int $requestMinStatus,
        private bool $benchmarkEnabled,
    ) {
    }

    /**
     * Returns the shared number of daily log files retained by built-in channels.
     */
    public function retentionDays(): int
    {
        return $this->retentionDays;
    }

    /**
     * Reports whether HTTP request records are written to their dedicated channel.
     */
    public function requestEnabled(): bool
    {
        return $this->requestEnabled;
    }

    /**
     * Returns the minimum HTTP status code eligible for request logging, or zero for every response.
     */
    public function requestMinStatus(): int
    {
        return $this->requestMinStatus;
    }

    /**
     * Reports whether completed benchmark runs are written to their dedicated channel.
     */
    public function benchmarkEnabled(): bool
    {
        return $this->benchmarkEnabled;
    }
}
