<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware;
use Lemonade\Framework\Http\Response\HtmlMinifier;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HtmlMinifyMiddlewareTest extends TestCase
{
    public function testDisabledOrMissingConfigReturnsOriginalResponseInstance(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/');
        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'text/html')
            ->withBody($factory->createStream('<div> x </div>'));

        $handler = new FixedResponseHandler($response);

        $disabled = new HtmlMinifyMiddleware(new HtmlMinifier(), new Config([
            'html_minify' => ['enabled' => false],
        ]));
        $missing = new HtmlMinifyMiddleware(new HtmlMinifier(), new Config([]));

        self::assertSame($response, $disabled->process($request, $handler));
        self::assertSame($response, $missing->process($request, $handler));
    }

    public function testNonHtmlResponseReturnsOriginalInstance(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/');
        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{ "a": 1 }'));

        $middleware = new HtmlMinifyMiddleware(new HtmlMinifier(), new Config([
            'html_minify' => ['enabled' => true],
        ]));

        self::assertSame($response, $middleware->process($request, new FixedResponseHandler($response)));
    }

    public function testHtmlWithCharsetCanBeMinifiedAndContentLengthIsReplaced(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/');
        $html = "<div>\n  <span> A </span>\n</div>";
        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Content-Length', (string) strlen($html))
            ->withBody($factory->createStream($html));

        $middleware = new HtmlMinifyMiddleware(new HtmlMinifier(), new Config([
            'html_minify' => ['enabled' => true],
        ]));

        $processed = $middleware->process($request, new FixedResponseHandler($response));
        $minifiedBody = (string) $processed->getBody();

        self::assertNotSame($response, $processed);
        self::assertSame('<div><span> A </span></div>', $minifiedBody);
        self::assertSame((string) strlen($minifiedBody), $processed->getHeaderLine('Content-Length'));
        self::assertSame(['Content-Length' => [(string) strlen($minifiedBody)]], [
            'Content-Length' => $processed->getHeader('Content-Length'),
        ]);
    }

    public function testWhitespaceOnlyBodyReturnsOriginalInstance(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/');
        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'text/html')
            ->withBody($factory->createStream(" \n\t "));

        $middleware = new HtmlMinifyMiddleware(new HtmlMinifier(), new Config([
            'html_minify' => ['enabled' => true],
        ]));

        self::assertSame($response, $middleware->process($request, new FixedResponseHandler($response)));
    }

    public function testSameMinifiedContentReturnsOriginalInstanceAndRequestIsPassedToHandler(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/');
        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'text/html')
            ->withBody($factory->createStream('<div>x</div>'));

        $handler = new FixedResponseHandler($response);
        $middleware = new HtmlMinifyMiddleware(new HtmlMinifier(), new Config([
            'html_minify' => ['enabled' => true],
        ]));

        $processed = $middleware->process($request, $handler);

        self::assertSame($response, $processed);
        self::assertSame($request, $handler->lastRequest);
    }
}

final class FixedResponseHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $lastRequest = null;

    public function __construct(private readonly ResponseInterface $response) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }
}
