<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds one composable layer of inbound HTTP server trust configuration.
 */
final class HttpServerConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty definition that contributes only explicitly configured values.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the registry key under which inbound HTTP server definitions are collected.
     */
    public static function moduleKey(): string
    {
        return 'http_server';
    }

    /**
     * Replaces the exact IP addresses and CIDR networks trusted as immediate proxy peers.
     *
     * @param list<string> $proxies
     */
    public function trustedProxies(array $proxies): self
    {
        return $this->set('trusted_proxies', array_values($proxies));
    }

    /**
     * Selects the only forwarding-header family accepted from trusted proxy peers.
     */
    public function forwardedHeaderProfile(ForwardedHeaderProfile|string $profile): self
    {
        return $this->set(
            'forwarded_header_profile',
            $profile instanceof ForwardedHeaderProfile ? $profile->value : $profile,
        );
    }

    /**
     * Sets whether X-Forwarded-Prefix may be resolved after the proxy trust boundary is established.
     */
    public function trustForwardedPrefix(bool $trust = true): self
    {
        return $this->set('trust_forwarded_prefix', $trust);
    }

    /**
     * Replaces exact hostnames and suffix wildcards approved for request-derived origins.
     *
     * @param list<string> $hosts
     */
    public function allowedHosts(array $hosts): self
    {
        return $this->set('allowed_hosts', array_values($hosts));
    }
}
