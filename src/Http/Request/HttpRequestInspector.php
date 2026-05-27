<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

use Psr\Http\Message\ServerRequestInterface;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

final class HttpRequestInspector
{
    private const UNKNOWN = 'UNKNOWN';

    public function clientIp(ServerRequestInterface $request): ?string
    {
        $candidates = array_values(array_filter([
            $this->extractFromForwarded($request->getHeaderLine('Forwarded')),
            $this->extractFromForwardedFor($request->getHeaderLine('X-Forwarded-For')),
            $this->validateIp($request->getHeaderLine('Client-IP')),
            $this->validateIp($request->getHeaderLine('X-Forwarded')),
            $this->validateIp($request->getHeaderLine('X-Cluster-Client-IP')),
            $this->validateIp($request->getHeaderLine('Forwarded-For')),
            $this->remoteAddr($request),
        ], static fn(?string $ip): bool => $ip !== null));

        foreach ($candidates as $ip) {
            if (!$this->isPrivateIp($ip)) {
                return $ip;
            }
        }

        return $candidates[0] ?? null;
    }

    public function anonymizedIp(ServerRequestInterface $request): ?string
    {
        $ip = $this->clientIp($request);

        if ($ip === null) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);
            $parts[3] = '*';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $parts = explode(':', $ip);

            return implode(':', array_pad(array_slice($parts, 0, 3), 8, '*'));
        }

        return null;
    }

    public function viaProxy(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('Forwarded')
            || $request->hasHeader('X-Forwarded-For')
            || $request->hasHeader('Client-IP')
            || $request->hasHeader('X-Forwarded')
            || $request->hasHeader('X-Cluster-Client-IP');
    }

    public function forwardedFor(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('X-Forwarded-For');
    }

    public function userAgent(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('User-Agent');
    }

    public function referer(ServerRequestInterface $request): ?string
    {
        foreach ([
            'Referer',
            'X-Forwarded-Referer',
            'X-Original-Referer',
        ] as $header) {
            $value = $request->getHeaderLine($header);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function host(ServerRequestInterface $request): string
    {
        $host = $request->getUri()->getHost();

        return $host !== '' ? $host : self::UNKNOWN;
    }

    public function accept(ServerRequestInterface $request): string
    {
        $accept = $request->getHeaderLine('Accept');

        return $accept !== '' ? $accept : self::UNKNOWN;
    }

    public function acceptLanguage(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('Accept-Language');
    }

    public function gzipAccepted(ServerRequestInterface $request): bool
    {
        return str_contains($request->getHeaderLine('Accept-Encoding'), 'gzip');
    }

    public function contentLength(ServerRequestInterface $request): int
    {
        $length = $request->getHeaderLine('Content-Length');

        return is_numeric($length) ? (int) $length : 0;
    }

    public function queryParamsCount(ServerRequestInterface $request): int
    {
        return count($request->getQueryParams());
    }

    public function parsedBodyParamsCount(ServerRequestInterface $request): int
    {
        $body = $request->getParsedBody();

        return is_array($body) ? count($body) : 0;
    }

    public function requestId(ServerRequestInterface $request): ?string
    {
        $value = $request->getHeaderLine('X-Request-Id');

        if ($value !== '') {
            return $value;
        }

        $serverParams = $request->getServerParams();
        $requestId = $serverParams['REQUEST_ID'] ?? null;

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    public function fingerprint(ServerRequestInterface $request): string
    {
        $raw = implode('|', [
            strtolower($this->userAgent($request)),
            strtolower($this->accept($request)),
            strtolower($this->acceptLanguage($request)),
            $this->anonymizedIp($request) ?? '',
        ]);

        return hash('sha256', $raw);
    }

    private function remoteAddr(ServerRequestInterface $request): ?string
    {
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddr) ? $this->validateIp($remoteAddr) : null;
    }

    private function extractFromForwarded(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/for="?\[?([^\]";,]+)\]?"?/i', $value, $matches) !== 1) {
            return null;
        }

        return $this->validateIp($matches[1]);
    }

    private function extractFromForwardedFor(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return $this->validateIp(trim(explode(',', $value)[0]));
    }

    private function validateIp(string $ip): ?string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return str_starts_with($ip, '127.') || $ip === '::1';
    }
}
