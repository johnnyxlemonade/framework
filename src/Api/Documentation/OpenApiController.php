<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Documentation;

use JsonException;
use Lemonade\Framework\Http\HttpStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * Serializes the current generated OpenAPI document as a UTF-8 JSON response.
 */
final readonly class OpenApiController
{
    /**
     * Binds document generation to PSR-17 response and stream creation.
     */
    public function __construct(
        private OpenApiGenerator $generator,
        private Psr17Factory $psr17,
    ) {
    }

    /**
     * Returns the current OpenAPI document without escaping slashes or Unicode characters.
     *
     * @throws JsonException When the generated document cannot be encoded as JSON.
     */
    public function show(): ResponseInterface
    {
        $body = (string) json_encode(
            $this->generator->generate(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->psr17
            ->createResponse(HttpStatus::OK->value)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->psr17->createStream($body));
    }
}
