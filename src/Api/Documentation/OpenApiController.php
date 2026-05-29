<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Documentation;

use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;

final class OpenApiController
{
    public function __construct(
        private readonly OpenApiGenerator $generator,
        private readonly ApiResponseFactory $responses,
    ) {}

    public function show(): ResponseInterface
    {
        return $this->responses->json($this->generator->generate());
    }
}
