<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds one composable layer of outbound HTTP client configuration.
 */
final class HttpClientConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty definition that contributes only explicitly configured values.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the registry key under which HTTP client definitions are collected.
     */
    public static function moduleKey(): string
    {
        return 'http_client';
    }

    /**
     * Sets the total request timeout in seconds.
     */
    public function timeout(float|int $seconds): self
    {
        return $this->set('timeout', $seconds);
    }

    /**
     * Sets the connection-establishment timeout in seconds.
     */
    public function connectTimeout(float|int $seconds): self
    {
        return $this->set('connect_timeout', $seconds);
    }

    /**
     * Sets whether the selected client transport verifies peer TLS certificates.
     */
    public function verifySsl(bool $verifySsl = true): self
    {
        return $this->set('verify_ssl', $verifySsl);
    }
}
