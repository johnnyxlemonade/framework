<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Response;

use JsonException;
use Lemonade\Framework\Http\HttpStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * Creates the framework API envelope as immutable UTF-8 JSON responses.
 */
final readonly class ApiResponseFactory
{
    /**
     * Uses one PSR-17 implementation for response and body stream creation.
     */
    public function __construct(
        private Psr17Factory $responseFactory,
    ) {
    }

    /**
     * Encodes data and optional metadata under the stable API response envelope.
     *
     * @param array<string, mixed> $meta
     * @throws JsonException When the supplied payload cannot be encoded as JSON.
     */
    public function json(mixed $data, int $status = HttpStatus::OK->value, array $meta = []): ResponseInterface
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
     * Creates a successful 200 response using the standard API envelope.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @throws JsonException When the supplied payload cannot be encoded as JSON.
     */
    public function ok(array $data, array $meta = []): ResponseInterface
    {
        return $this->json($data, HttpStatus::OK->value, $meta);
    }
}
