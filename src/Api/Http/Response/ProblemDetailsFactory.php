<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Response;

use JsonException;
use Lemonade\Framework\Http\HttpStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Creates RFC 9457-style Problem Details responses without exposing exception internals.
 */
final readonly class ProblemDetailsFactory
{
    /**
     * Uses one PSR-17 implementation for response and body stream creation.
     */
    public function __construct(
        private Psr17Factory $responseFactory,
    ) {
    }

    /**
     * Creates a problem response whose instance identifies the current request path.
     *
     * @param array<string, mixed> $extensions
     * @throws JsonException When the problem payload or extensions cannot be encoded as JSON.
     */
    public function create(
        int $status,
        string $title,
        string $detail,
        ServerRequestInterface $request,
        string $type = 'about:blank',
        array $extensions = [],
    ): ResponseInterface {
        $payload = array_merge([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getUri()->getPath(),
        ], $extensions);

        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/problem+json; charset=utf-8')
            ->withBody($this->responseFactory->createStream($body));
    }

    /**
     * Returns a 401 bearer challenge for a request lacking a valid API identity.
     *
     * @throws JsonException When the problem payload cannot be encoded as JSON.
     */
    public function unauthenticated(ServerRequestInterface $request): ResponseInterface
    {
        return $this->create(
            status: HttpStatus::UNAUTHORIZED->value,
            title: 'Unauthenticated',
            detail: 'Authentication is required to access this API endpoint.',
            request: $request,
            type: 'https://lemonade.dev/problems/unauthenticated',
        )->withHeader('WWW-Authenticate', 'Bearer');
    }

    /**
     * Returns a 403 response without disclosing which authorization condition failed.
     *
     * @throws JsonException When the problem payload cannot be encoded as JSON.
     */
    public function forbidden(ServerRequestInterface $request): ResponseInterface
    {
        return $this->create(
            status: HttpStatus::FORBIDDEN->value,
            title: 'Forbidden',
            detail: 'You are not allowed to access this API endpoint.',
            request: $request,
            type: 'https://lemonade.dev/problems/forbidden',
        );
    }

    /**
     * Returns a 404 problem tied to the current request path.
     *
     * @throws JsonException When the problem payload cannot be encoded as JSON.
     */
    public function notFound(ServerRequestInterface $request): ResponseInterface
    {
        return $this->create(
            status: HttpStatus::NOT_FOUND->value,
            title: 'Not Found',
            detail: 'The requested resource was not found.',
            request: $request,
            type: 'https://lemonade.dev/problems/not-found',
        );
    }

    /**
     * Returns a generic 500 problem that does not expose the captured exception.
     *
     * @throws JsonException When the problem payload cannot be encoded as JSON.
     */
    public function internalServerError(ServerRequestInterface $request): ResponseInterface
    {
        return $this->create(
            status: HttpStatus::INTERNAL_SERVER_ERROR->value,
            title: 'Internal Server Error',
            detail: 'An unexpected error occurred while processing this API request.',
            request: $request,
            type: 'https://lemonade.dev/problems/internal-server-error',
        );
    }
}
