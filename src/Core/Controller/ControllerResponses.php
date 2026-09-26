<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use JsonException;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Http\HttpStatus;
use Psr\Http\Message\ResponseInterface;

final class ControllerResponses
{
    public function __construct(
        private readonly ResponseBuilder $builder,
    ) {}

    public function text(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->text($content, $status);
    }

    public function html(string $content, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->html($content, $status);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    public function json(array $payload, int $status = HttpStatus::OK->value): ResponseInterface
    {
        return $this->builder->json($payload, $status);
    }

    public function redirect(string $to, int $status = HttpStatus::FOUND->value): ResponseInterface
    {
        return $this->builder->redirect($to, $status);
    }

    public function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return $this->builder->download($filePath, $downloadName, $contentType);
    }

    public function response(
        string $content = '',
        int $status = HttpStatus::OK->value,
        string $contentType = 'text/html; charset=UTF-8',
    ): ResponseInterface {
        return $this->builder->response($content, $status, $contentType);
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
        return $this->builder->stream($producer, $status, $contentType, $headers);
    }
}
