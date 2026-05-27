<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Http\Response\HtmlMinifier;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HtmlMinifyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HtmlMinifier $minifier,
        private readonly Config $config,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (! $this->enabled()) {
            return $response;
        }

        if (! $this->isHtmlResponse($response)) {
            return $response;
        }

        $html = (string) $response->getBody();

        if (trim($html) === '') {
            return $response;
        }

        $minified = $this->minifier->minify($html);

        if ($minified === $html) {
            return $response;
        }

        return $response
            ->withBody(Stream::create($minified))
            ->withoutHeader('Content-Length')
            ->withHeader('Content-Length', (string) strlen($minified));
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('html_minify.enabled', false);
    }

    private function isHtmlResponse(ResponseInterface $response): bool
    {
        $contentType = strtolower($response->getHeaderLine('Content-Type'));

        if ($contentType === '') {
            return false;
        }

        return str_contains($contentType, 'text/html');
    }
}
