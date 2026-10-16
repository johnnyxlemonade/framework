<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Http\Request\ServerHelper;

/**
 * Resolves an application base URL from explicit configuration or current request metadata.
 *
 * Explicit configuration takes precedence, while relative paths are joined at one URL boundary.
 */
final readonly class BaseUrlResolver
{
    /**
     * Binds URL resolution to the application's resolved configuration.
     */
    public function __construct(
        private AppConfig $config,
    ) {
    }

    /**
     * Resolves the application base URL and optionally appends a relative path.
     */
    public function __invoke(string $path = ''): string
    {
        return $this->baseUrl($path);
    }

    /**
     * Uses configured URL data when available and otherwise derives scheme and host from the request.
     */
    public function baseUrl(string $path = ''): string
    {
        $baseUrl = $this->configuredBaseUrl() ?? $this->baseUrlFromServer();

        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function configuredBaseUrl(): ?string
    {
        $baseUrl = $this->config->baseUrl();
        if ($baseUrl === null || trim($baseUrl) === '') {
            return null;
        }

        return rtrim($this->normalizeBaseUrl($baseUrl), '/');
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);

        if (str_starts_with($baseUrl, 'http://') || str_starts_with($baseUrl, 'https://')) {
            return $baseUrl;
        }

        return ServerHelper::scheme() . '://' . $baseUrl;
    }

    private function baseUrlFromServer(): string
    {
        return ServerHelper::scheme() . '://' . ServerHelper::host();
    }
}
