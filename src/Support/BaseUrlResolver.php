<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Http\Request\ServerHelper;

final class BaseUrlResolver
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function __invoke(string $path = ''): string
    {
        return $this->baseUrl($path);
    }

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
        $baseUrl = $this->config->get('app.base_url');

        if (!is_string($baseUrl) || trim($baseUrl) === '') {
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

        return $this->scheme() . '://' . $baseUrl;
    }

    private function baseUrlFromServer(): string
    {
        return $this->scheme() . '://' . $this->host();
    }

    private function scheme(): string
    {
        $forwardedProto = strtolower(trim(ServerHelper::get('HTTP_X_FORWARDED_PROTO')));

        if ($forwardedProto !== '') {
            $proto = trim(explode(',', $forwardedProto)[0]);

            if ($proto === 'https') {
                return 'https';
            }

            if ($proto === 'http') {
                return 'http';
            }
        }

        $https = strtolower(ServerHelper::get('HTTPS'));

        if ($https !== '' && $https !== 'off') {
            return 'https';
        }

        if (ServerHelper::get('SERVER_PORT') === '443') {
            return 'https';
        }

        return 'http';
    }

    private function host(): string
    {
        $host = trim(ServerHelper::get('HTTP_HOST'));

        if ($host !== '') {
            return $host;
        }

        $serverName = trim(ServerHelper::get('SERVER_NAME'));

        if ($serverName !== '') {
            return $serverName;
        }

        return 'localhost';
    }
}
