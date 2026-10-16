<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Response;

use JsonException;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Http\HttpStatus;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides application controllers with consistently typed responses and one-pass streamed bodies.
 */
final readonly class Responses
{
    /**
     * Delegates response and body creation to the framework's PSR-17-backed builder.
     */
    public function __construct(
        private ResponseBuilder $builder,
    ) {
    }

    /**
     * Returns UTF-8 plain text with the requested status.
     */
    public function text(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->text($content, $status);
    }

    /**
     * Returns UTF-8 HTML with the requested status.
     */
    public function html(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->html($content, $status);
    }

    /**
     * Encodes a JSON response while preserving Unicode characters and slashes.
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
     * Returns an empty response carrying the target Location header.
     */
    public function redirect(string $to, int $status = HttpStatus::FOUND->value): ResponseInterface
    {
        return $this->builder->redirect($to, $status);
    }

    /**
     * Opens a file-backed response with attachment and private no-store headers.
     */
    public function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return $this->builder->download($filePath, $downloadName, $contentType);
    }

    /**
     * Returns a one-pass response whose string-chunk producer starts on first read.
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
