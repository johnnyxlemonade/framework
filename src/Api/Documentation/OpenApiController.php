<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Documentation;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

final class OpenApiController
{
    public function __construct(
        private readonly OpenApiGenerator $generator,
        private readonly Psr17Factory $psr17,
    ) {}

    public function show(): ResponseInterface
    {
        $body = (string) json_encode(
            $this->generator->generate(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->psr17
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->psr17->createStream($body));
    }
}
