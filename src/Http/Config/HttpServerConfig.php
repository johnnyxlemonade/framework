<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

/**
 * Carries the immutable trust policy used to resolve externally visible request metadata.
 */
final readonly class HttpServerConfig
{
    /**
     * Captures normalized proxy networks, header policy and host allowlist.
     *
     * @param list<non-empty-string> $trustedProxies
     * @param list<non-empty-string> $allowedHosts
     */
    public function __construct(
        private array $trustedProxies,
        private ForwardedHeaderProfile $forwardedHeaderProfile,
        private bool $trustForwardedPrefix,
        private array $allowedHosts,
    ) {
    }

    /**
     * Returns the exact IP addresses and CIDR networks permitted to supply forwarding metadata.
     *
     * @return list<non-empty-string>
     */
    public function trustedProxies(): array
    {
        return $this->trustedProxies;
    }

    /**
     * Returns the single forwarding-header family accepted from a trusted peer.
     */
    public function forwardedHeaderProfile(): ForwardedHeaderProfile
    {
        return $this->forwardedHeaderProfile;
    }

    /**
     * Reports whether a trusted X-Forwarded-Prefix value may contribute the external path prefix.
     */
    public function trustsForwardedPrefix(): bool
    {
        return $this->trustForwardedPrefix;
    }

    /**
     * Returns exact hostnames and suffix wildcards approved for canonical request metadata.
     *
     * An empty list approves no request-derived host.
     *
     * @return list<non-empty-string>
     */
    public function allowedHosts(): array
    {
        return $this->allowedHosts;
    }
}
