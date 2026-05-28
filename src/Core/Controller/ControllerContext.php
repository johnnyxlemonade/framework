<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ControllerContext
{
    private RequestData $requestData;
    private ResponseBuilder $responseBuilder;

    public function __construct(
        private readonly ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        $this->requestData = new RequestData($request);
        $this->responseBuilder = new ResponseBuilder($responseFactory, $streamFactory);
    }

    public function request(): ServerRequestInterface
    {
        return $this->request;
    }

    public function requestData(): RequestData
    {
        return $this->requestData;
    }

    public function responseBuilder(): ResponseBuilder
    {
        return $this->responseBuilder;
    }
}
