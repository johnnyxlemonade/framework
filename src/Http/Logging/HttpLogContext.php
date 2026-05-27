<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Logging;

use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HttpLogContext
{
    public function __construct(
        private readonly HttpRequestInspector $inspector,
    ) {}

    /**
     * @return array{
     *     method: string,
     *     uri: string,
     *     path: string,
     *     query: string,
     *     host: string,
     *     ip: string|null,
     *     ip_anonymized: string|null,
     *     via_proxy: bool,
     *     forwarded_for: string,
     *     user_agent: string,
     *     referer: string|null,
     *     accept: string,
     *     accept_language: string,
     *     gzip_accepted: bool,
     *     content_length: int,
     *     query_params_count: int,
     *     parsed_body_params_count: int,
     *     request_id: string|null,
     *     fingerprint: string
     * }
     */
    public function request(ServerRequestInterface $request): array
    {
        return [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'path' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
            'host' => $this->inspector->host($request),
            'ip' => $this->inspector->clientIp($request),
            'ip_anonymized' => $this->inspector->anonymizedIp($request),
            'via_proxy' => $this->inspector->viaProxy($request),
            'forwarded_for' => $this->inspector->forwardedFor($request),
            'user_agent' => $this->inspector->userAgent($request),
            'referer' => $this->inspector->referer($request),
            'accept' => $this->inspector->accept($request),
            'accept_language' => $this->inspector->acceptLanguage($request),
            'gzip_accepted' => $this->inspector->gzipAccepted($request),
            'content_length' => $this->inspector->contentLength($request),
            'query_params_count' => $this->inspector->queryParamsCount($request),
            'parsed_body_params_count' => $this->inspector->parsedBodyParamsCount($request),
            'request_id' => $this->inspector->requestId($request),
            'fingerprint' => $this->inspector->fingerprint($request),
        ];
    }

    /**
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
            'duration_ms' => $durationMs,
            'response_size' => $response->getBody()->getSize(),
        ];
    }
}
