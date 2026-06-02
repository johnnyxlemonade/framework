<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Response;

use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

final class ApiResponseFactory
{
    public function __construct(
        private readonly Psr17Factory $responseFactory,
    ) {}

    /**
     * @param array<string, mixed> $meta
     *
     * @throws JsonException
     */
    public function json(mixed $data, int $status = 200, array $meta = []): ResponseInterface
    {
        $payload = [
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->responseFactory->createStream($body));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     *
     * @throws JsonException
     */
    public function ok(array $data, array $meta = []): ResponseInterface
    {
        return $this->json($data, 200, $meta);
    }
}
