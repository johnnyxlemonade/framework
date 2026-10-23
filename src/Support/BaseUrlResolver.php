<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Http\Request\Exception\InvalidRequestMetadataException;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use RuntimeException;

/**
 * Resolves absolute application URLs only from explicit configuration or validated request metadata.
 *
 * An explicit app base URL always wins and is never combined with a forwarded request prefix.
 */
final readonly class BaseUrlResolver
{
    /**
     * Binds URL resolution to application configuration and an optional active request context.
     */
    public function __construct(
        private AppConfig $config,
        private ?ResolvedRequestContext $requestContext = null,
    ) {
    }

    /**
     * Resolves the canonical base URL and appends an optional relative path.
     */
    public function __invoke(string $path = ''): string
    {
        return $this->baseUrl($path);
    }

    /**
     * Uses explicit configuration first and otherwise requires an allowlisted request host.
     *
     * @throws InvalidRequestMetadataException When an active request has no allowlisted host.
     * @throws RuntimeException When request-derived resolution is attempted outside an HTTP request scope.
     */
    public function baseUrl(string $path = ''): string
    {
        $baseUrl = $this->configuredBaseUrl() ?? $this->requestBaseUrl();

        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function configuredBaseUrl(): ?string
    {
        $baseUrl = $this->config->baseUrl();
        if ($baseUrl === null) {
            return null;
        }

        return rtrim($baseUrl, '/');
    }

    private function requestBaseUrl(): string
    {
        if (!$this->requestContext instanceof ResolvedRequestContext) {
            throw new RuntimeException(
                'Cannot generate an absolute URL without app.base_url outside an HTTP request. Configure app.base_url explicitly.',
            );
        }

        $host = $this->requestContext->host();
        if ($host === null) {
            throw new InvalidRequestMetadataException(
                'Cannot generate an absolute URL from this request host. Configure app.base_url or add the exact host to http_server.allowed_hosts.',
            );
        }

        $authority = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $port = $this->requestContext->port();
        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $this->requestContext->scheme()
            . '://'
            . $authority
            . $this->requestContext->prefix();
    }
}
