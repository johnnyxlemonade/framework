<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class LoggingConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty logging definition for framework defaults or application policy.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the YAML module name consumed by the typed logging resolver.
     */
    public static function moduleKey(): string
    {
        return 'logging';
    }

    /**
     * Sets the shared retention period used by every built-in file channel.
     */
    public function retentionDays(int $days): self
    {
        return $this->set('retention_days', $days);
    }

    /**
     * Enables or disables optional HTTP request file logging.
     */
    public function requestEnabled(bool $enabled = true): self
    {
        return $this->set('request.enabled', $enabled);
    }

    /**
     * Sets the lowest response status included in optional request logging.
     */
    public function requestMinStatus(int $statusCode): self
    {
        return $this->set('request.min_status', $statusCode);
    }

    /**
     * Enables or disables optional benchmark-run file logging.
     */
    public function benchmarkEnabled(bool $enabled = true): self
    {
        return $this->set('benchmark.enabled', $enabled);
    }
}
