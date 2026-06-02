<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Response;

use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProblemDetailsFactory
{
    public function __construct(
        private readonly Psr17Factory $responseFactory,
    ) {}

    /**
     * @param array<string, mixed> $extensions
     *
     * @throws JsonException
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

    public function unauthenticated(ServerRequestInterface $request): ResponseInterface
    {
        return $this->create(
            status: 401,
            title: 'Unauthenticated',
            detail: 'Authentication is required to access this API endpoint.',
            request: $request,
            type: 'https://lemonade.dev/problems/unauthenticated',
        )->withHeader('WWW-Authenticate', 'Bearer');
    }

    public function forbidden(ServerRequestInterface $request): ResponseInterface
    {
        return $this->create(
            status: 403,
            title: 'Forbidden',
            detail: 'You are not allowed to access this API endpoint.',
            request: $request,
            type: 'https://lemonade.dev/problems/forbidden',
        );
    }

    public function notFound(ServerRequestInterface $request): ResponseInterface
    {
        return $this->create(
            status: 404,
            title: 'Not Found',
            detail: 'The requested resource was not found.',
            request: $request,
            type: 'https://lemonade.dev/problems/not-found',
        );
    }
}
