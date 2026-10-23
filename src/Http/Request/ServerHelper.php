<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Exposes raw server parameters separately from trust-resolved request origin metadata.
 *
 * The helper never consults global state and never reparses forwarding headers.
 */
final readonly class ServerHelper
{
    /**
     * Binds raw server access and trusted origin access to one explicit request.
     */
    public function __construct(
        private ServerRequestInterface $request,
        private ResolvedRequestContext $context,
    ) {
    }

    /**
     * Returns one raw server parameter as text or the supplied fallback.
     */
    public function get(string $key, string $default = ''): string
    {
        $value = $this->request->getServerParams()[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * Reports whether one non-empty raw string server parameter is available.
     */
    public function has(string $key): bool
    {
        $value = $this->request->getServerParams()[$key] ?? null;

        return is_string($value) && $value !== '';
    }

    /**
     * Returns the raw server-parameter snapshot carried by the explicit PSR request.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->request->getServerParams();
    }

    /**
     * Returns the allowlisted host or the explicit fallback when no host was trusted.
     */
    public function host(string $default = 'localhost'): string
    {
        return $this->context->host() ?? $default;
    }

    /**
     * Returns the scheme established by the central request trust policy.
     *
     * @return 'http'|'https'
     */
    public function scheme(): string
    {
        return $this->context->scheme();
    }

    /**
     * Reports whether the central request trust policy resolved HTTPS.
     */
    public function isHttps(): bool
    {
        return $this->context->secure();
    }

    /**
     * Returns the verified client address or the explicit fallback when unavailable.
     */
    public function clientIp(?string $default = null): ?string
    {
        return $this->context->clientIp() ?? $default;
    }
}
