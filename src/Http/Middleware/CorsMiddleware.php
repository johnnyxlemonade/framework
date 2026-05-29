<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Config;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->config->bool('cors.enabled', false)) {
            return $handler->handle($request);
        }

        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin === '') {
            return $handler->handle($request);
        }

        $allowedOrigins = $this->stringList('cors.allowed_origins');
        $allowCredentials = $this->config->bool('cors.allow_credentials', false);

        if ($allowCredentials && in_array('*', $allowedOrigins, true)) {
            throw new \InvalidArgumentException(
                'Invalid CORS configuration: wildcard origin is not allowed when credentials are enabled.',
            );
        }

        $preflight = $this->isPreflightRequest($request);
        $originAllowed = in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true);

        if (!$originAllowed) {
            if ($preflight) {
                return $this->withVaryOrigin($this->responseFactory->createResponse(403));
            }

            return $handler->handle($request);
        }

        if ($preflight) {
            return $this->buildPreflightResponse($origin, $allowCredentials);
        }

        $response = $handler->handle($request);

        return $this->applyCorsResponseHeaders($response, $origin, $allowCredentials, false);
    }

    private function isPreflightRequest(ServerRequestInterface $request): bool
    {
        return strtoupper($request->getMethod()) === 'OPTIONS'
            && trim($request->getHeaderLine('Origin')) !== ''
            && trim($request->getHeaderLine('Access-Control-Request-Method')) !== '';
    }

    private function buildPreflightResponse(string $origin, bool $allowCredentials): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(204);

        return $this->applyCorsResponseHeaders($response, $origin, $allowCredentials, true);
    }

    private function applyCorsResponseHeaders(
        ResponseInterface $response,
        string $origin,
        bool $allowCredentials,
        bool $preflight,
    ): ResponseInterface {
        $allowedOrigins = $this->stringList('cors.allowed_origins');
        $allowedMethods = $this->stringList('cors.allowed_methods');
        $allowedHeaders = $this->stringList('cors.allowed_headers');
        $exposedHeaders = $this->stringList('cors.exposed_headers');
        $maxAge = $this->maxAge();

        $allowOriginHeader = in_array('*', $allowedOrigins, true) && !$allowCredentials ? '*' : $origin;
        $response = $response->withHeader('Access-Control-Allow-Origin', $allowOriginHeader);

        if ($allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($preflight) {
            if ($allowedMethods !== []) {
                $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
            }

            if ($allowedHeaders !== []) {
                $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
            }

            if ($maxAge !== null) {
                $response = $response->withHeader('Access-Control-Max-Age', (string) $maxAge);
            }
        } elseif ($exposedHeaders !== []) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
        }

        return $this->withVaryOrigin($response);
    }

    private function withVaryOrigin(ResponseInterface $response): ResponseInterface
    {
        $current = $response->getHeaderLine('Vary');

        if ($current === '') {
            return $response->withHeader('Vary', 'Origin');
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $current)),
            static fn(string $part): bool => $part !== '',
        ));

        $normalized = array_map(
            static fn(string $part): string => strtolower($part),
            $parts,
        );

        if (!in_array('origin', $normalized, true)) {
            $parts[] = 'Origin';
        }

        return $response->withHeader('Vary', implode(', ', $parts));
    }

    private function maxAge(): ?int
    {
        $value = $this->config->get('cors.max_age');

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        $raw = $this->config->array($key, []);
        $values = [];

        foreach ($raw as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }

            $values[] = $value;
        }

        return array_values(array_unique($values));
    }
}
