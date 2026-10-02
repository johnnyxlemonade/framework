<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Response;

use JsonException;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Http\HttpStatus;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides application controllers with framework response factories including one-pass streamed bodies
 */
final readonly class Responses
{
    /**
     * Initializes the facade for standard HTTP responses
     */
    public function __construct(
        private ResponseBuilder $builder,
    ) {
    }

    /**
     * Creates a text response
     */
    public function text(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->text($content, $status);
    }

    /**
     * Creates an HTML response
     */
    public function html(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->html($content, $status);
    }

    /**
     * Creates a JSON response using the framework encoding policy
     *
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    public function json(array $payload, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->json($payload, $status);
    }

    /**
     * Creates a redirect response
     */
    public function redirect(string $to, int $status = HttpStatus::FOUND->value): ResponseInterface
    {
        return $this->builder->redirect($to, $status);
    }

    /**
     * Creates a file download response
     */
    public function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return $this->builder->download($filePath, $downloadName, $contentType);
    }

    /**
     * Creates a one-pass response from a lazy producer of string chunks
     *
     * @param callable(): iterable<string> $producer
     * @param array<string, string> $headers
     */
    public function stream(
        callable $producer,
        int $status = HttpStatus::OK->value,
        string $contentType = 'text/plain; charset=UTF-8',
        array $headers = [],
    ): ResponseInterface {
        return $this->builder->stream($producer, $status, $contentType, $headers);
    }
}
