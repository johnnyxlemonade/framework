<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\FrameworkInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PoweredByMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly FrameworkInfo $frameworkInfo,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler
            ->handle($request)
            ->withHeader(
                $this->frameworkInfo->poweredByHeader(),
                $this->frameworkInfo->poweredByValue(),
            );
    }
}
