<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

use Psr\Http\Message\ServerRequestInterface;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

/**
 * Derives logging and content-negotiation metadata without reparsing proxy trust inputs.
 */
final readonly class HttpRequestInspector
{
    private const string UNKNOWN = 'UNKNOWN';

    /**
     * Uses the request-scoped trust result when security-sensitive metadata is requested.
     */
    public function __construct(
        private ?ResolvedRequestContext $context = null,
    ) {
    }

    /**
     * Returns the verified client address, or null outside an initialized HTTP request scope.
     */
    public function clientIp(ServerRequestInterface $request): ?string
    {
        unset($request);

        return $this->context?->clientIp();
    }

    /**
     * Masks the identifying suffix of the selected IPv4 or IPv6 client address.
     */
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

    /**
     * Reports whether the verified immediate peer is an explicitly trusted proxy.
     */
    public function viaProxy(ServerRequestInterface $request): bool
    {
        unset($request);

        return $this->context?->viaTrustedProxy() ?? false;
    }

    /**
     * Returns the verified client address for proxied requests, or an empty string otherwise.
     */
    public function forwardedFor(ServerRequestInterface $request): string
    {
        return $this->viaProxy($request) ? ($this->clientIp($request) ?? '') : '';
    }

    /**
     * Returns the request user-agent header line.
     */
    public function userAgent(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('User-Agent');
    }

    /**
     * Returns the request's standard referrer value when present.
     */
    public function referer(ServerRequestInterface $request): ?string
    {
        $value = $request->getHeaderLine('Referer');

        return $value !== '' ? $value : null;
    }

    /**
     * Returns the allowlisted request host or an explicit unknown marker.
     */
    public function host(ServerRequestInterface $request): string
    {
        unset($request);

        return $this->context?->host() ?? self::UNKNOWN;
    }

    /**
     * Returns the Accept header or an explicit unknown marker when it is absent.
     */
    public function accept(ServerRequestInterface $request): string
    {
        $accept = $request->getHeaderLine('Accept');

        return $accept !== '' ? $accept : self::UNKNOWN;
    }

    /**
     * Returns whether JSON is an explicitly preferred acceptable response representation.
     *
     * This inspects only the Accept header. It does not infer a response format
     * from the request Content-Type, an AJAX header, or an Accept wildcard alone.
     */
    public function wantsJson(ServerRequestInterface $request): bool
    {
        $jsonQuality = null;
        $otherQuality = null;

        foreach ($request->getHeader('Accept') as $header) {
            foreach (explode(',', $header) as $value) {
                $mediaRange = $this->acceptMediaRange($value);
                if ($mediaRange === null) {
                    continue;
                }

                [$mediaType, $quality] = $mediaRange;

                if ($this->isJsonMediaType($mediaType)) {
                    if ($quality > 0.0) {
                        $jsonQuality = max($jsonQuality ?? 0.0, $quality);
                    }

                    continue;
                }

                if ($quality > 0.0) {
                    $otherQuality = max($otherQuality ?? 0.0, $quality);
                }
            }
        }

        return $jsonQuality !== null && ($otherQuality === null || $jsonQuality >= $otherQuality);
    }

    /**
     * Returns the raw Accept-Language header line.
     */
    public function acceptLanguage(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('Accept-Language');
    }

    /**
     * Reports whether the request's encoding declaration contains gzip support.
     */
    public function gzipAccepted(ServerRequestInterface $request): bool
    {
        return str_contains($request->getHeaderLine('Accept-Encoding'), 'gzip');
    }

    /**
     * Returns a numeric Content-Length value or zero for missing and invalid input.
     */
    public function contentLength(ServerRequestInterface $request): int
    {
        $length = $request->getHeaderLine('Content-Length');

        return is_numeric($length) ? (int) $length : 0;
    }

    /**
     * Counts the request's parsed query parameters.
     */
    public function queryParamsCount(ServerRequestInterface $request): int
    {
        return count($request->getQueryParams());
    }

    /**
     * Counts parsed body parameters when the body representation is an array.
     */
    public function parsedBodyParamsCount(ServerRequestInterface $request): int
    {
        $body = $request->getParsedBody();

        return is_array($body) ? count($body) : 0;
    }

    /**
     * Resolves a request identifier from its header before consulting server parameters.
     */
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

    /**
     * Hashes selected normalized request traits into a stable privacy-reduced fingerprint.
     */
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

    /**
     * @return array{0: string, 1: float}|null
     */
    private function acceptMediaRange(string $value): ?array
    {
        $parts = explode(';', $value);
        $mediaType = strtolower(trim((string) array_shift($parts)));

        if (preg_match('#^[^/\s;]+/[^/\s;]+$#', $mediaType) !== 1) {
            return null;
        }

        $quality = 1.0;

        foreach ($parts as $parameter) {
            $pair = explode('=', $parameter, 2);
            if (count($pair) !== 2 || strtolower(trim($pair[0])) !== 'q') {
                continue;
            }

            $qvalue = trim($pair[1]);
            if (preg_match('/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/', $qvalue) !== 1) {
                return null;
            }

            $quality = (float) $qvalue;
            break;
        }

        return [$mediaType, $quality];
    }

    private function isJsonMediaType(string $mediaType): bool
    {
        [, $subtype] = explode('/', $mediaType, 2);

        return $subtype === 'json' || str_ends_with($subtype, '+json');
    }

}
