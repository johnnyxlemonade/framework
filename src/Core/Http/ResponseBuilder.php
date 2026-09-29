<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Http;

use JsonException;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Psr\CallbackStream;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ResponseBuilder
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function text(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->response($content, $status, 'text/plain; charset=UTF-8');
    }

    public function html(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->response($content, $status, 'text/html; charset=UTF-8');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    public function json(array $payload, int $status = HttpStatus::OK->value): ResponseInterface
    {
        $content = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return $this->response($content, $status, 'application/json; charset=UTF-8');
    }

    public function redirect(string $to, int $status = HttpStatus::FOUND->value): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Location', $to);
    }

    public function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        $name = $downloadName !== null && $downloadName !== '' ? $downloadName : basename($filePath);
        $size = @filesize($filePath);

        $response = $this->responseFactory
            ->createResponse(HttpStatus::OK->value)
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . addslashes($name) . '"')
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Cache-Control', 'private, no-transform, no-store, must-revalidate');

        if (is_int($size)) {
            $response = $response->withHeader('Content-Length', (string) $size);
        }

        return $response->withBody($this->streamFactory->createStreamFromFile($filePath, 'r'));
    }

    public function response(
        string $content = '',
        int $status = HttpStatus::OK->value,
        string $contentType = 'text/html; charset=UTF-8',
    ): ResponseInterface {
        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', $contentType);

        if ($content === '') {
            return $response;
        }

        return $response->withBody(
            $this->streamFactory->createStream($content),
        );
    }

    /**
     * @param callable():void $producer
     * @param array<string, string> $headers
     */
    public function stream(
        callable $producer,
        int $status = HttpStatus::OK->value,
        string $contentType = 'text/plain; charset=UTF-8',
        array $headers = [],
    ): ResponseInterface {
        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody(CallbackStream::from($producer));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
