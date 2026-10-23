<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

/**
 * Captures one immutable, trust-aware view of externally visible request metadata.
 */
final readonly class ResolvedRequestContext
{
    /**
     * @var 'http'|'https'
     */
    private string $scheme;

    /**
     * Stores peer identity, resolved client identity and the validated external request origin.
     *
     * @param 'http'|'https' $scheme
     */
    public function __construct(
        private ?string $peerIp,
        private ?string $clientIp,
        private bool $viaTrustedProxy,
        string $scheme,
        private ?string $host,
        private ?int $port,
        private string $prefix,
    ) {
        $this->scheme = $scheme;
    }

    /**
     * Returns the direct network peer reported by the server request.
     */
    public function peerIp(): ?string
    {
        return $this->peerIp;
    }

    /**
     * Returns the first client address outside the trusted proxy chain.
     */
    public function clientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * Reports whether the immediate peer belongs to an explicitly trusted proxy network.
     */
    public function viaTrustedProxy(): bool
    {
        return $this->viaTrustedProxy;
    }

    /**
     * Returns the resolved external HTTP scheme.
     *
     * @return 'http'|'https'
     */
    public function scheme(): string
    {
        return $this->scheme;
    }

    /**
     * Reports whether the resolved external request used HTTPS.
     */
    public function secure(): bool
    {
        return $this->scheme === 'https';
    }

    /**
     * Returns an allowlisted host without brackets, or null when no request-derived host was approved.
     */
    public function host(): ?string
    {
        return $this->host;
    }

    /**
     * Returns a non-default external port, or null when the scheme default applies.
     */
    public function port(): ?int
    {
        return $this->port;
    }

    /**
     * Returns the normalized external application prefix, or an empty string when none was trusted.
     */
    public function prefix(): string
    {
        return $this->prefix;
    }
}
