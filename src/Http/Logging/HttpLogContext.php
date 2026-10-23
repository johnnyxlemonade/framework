<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Logging;

use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Produces an allowlisted HTTP context that excludes query strings, cookies, bodies, and
 * every header value except a validated request identifier.
 *
 * Paths and request identifiers are bounded and normalized before they cross the
 * framework logging boundary.
 */
final readonly class HttpLogContext
{
    private const int MAX_PATH_LENGTH = 2_048;

    private const int MAX_REQUEST_ID_LENGTH = 128;

    /**
     * Uses the shared request inspector to keep logging metadata normalization consistent.
     */
    public function __construct(
        private HttpRequestInspector $inspector,
    ) {
    }

    /**
     * Builds the safe request metadata shared by framework HTTP log records.
     *
     * @return array{
     *     method: string,
     *     path: string,
     *     request_id: string|null
     * }
     */
    public function request(ServerRequestInterface $request): array
    {
        return [
            'method' => $this->safeMethod($request->getMethod()),
            'path' => $this->safePath($request->getUri()->getPath()),
            'request_id' => $this->safeRequestId($this->inspector->requestId($request)),
        ];
    }

    /**
     * Extends request context with response status and non-negative processing duration.
     *
     * @return array<string, mixed>
     */
    public function requestResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $durationMs,
    ): array {
        return [
            ...$this->request($request),
            'status' => $response->getStatusCode(),
            'duration_ms' => max(0, $durationMs),
        ];
    }

    private function safeMethod(string $method): string
    {
        $method = strtoupper($method);

        if (preg_match("/^[!#$%&'*+.^_`|~0-9A-Z-]{1,32}$/D", $method) !== 1) {
            return 'UNKNOWN';
        }

        return $method;
    }

    private function safePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if (!mb_check_encoding($path, 'UTF-8')) {
            return '[invalid-path]';
        }

        $path = preg_replace('/[\x00-\x1F\x7F]/u', '', $path) ?? '[invalid-path]';

        if (mb_strlen($path, 'UTF-8') <= self::MAX_PATH_LENGTH) {
            return $path !== '' ? $path : '/';
        }

        return mb_substr($path, 0, self::MAX_PATH_LENGTH, 'UTF-8') . '...';
    }

    private function safeRequestId(?string $requestId): ?string
    {
        if ($requestId === null || strlen($requestId) > self::MAX_REQUEST_ID_LENGTH) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $requestId) === 1
            ? $requestId
            : null;
    }
}
