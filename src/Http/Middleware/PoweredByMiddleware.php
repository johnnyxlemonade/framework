<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\FrameworkInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds the framework's configured identification header to downstream responses.
 */
final readonly class PoweredByMiddleware implements MiddlewareInterface
{
    /**
     * Uses framework metadata as the header name and value source.
     */
    public function __construct(
        private FrameworkInfo $frameworkInfo,
    ) {
    }

    /**
     * Delegates request handling and replaces the identification response header.
     */
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
