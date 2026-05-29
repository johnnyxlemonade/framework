<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Http\Middleware\OptionsMiddleware;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class OptionsMiddlewareTest extends TestCase
{
    public function testNonOptionsRequestPassesThrough(): void
    {
        $router = new Router();
        $factory = new Psr17Factory();
        $middleware = new OptionsMiddleware($router, $factory);
        $handler = new RecordingHandler($factory->createResponse(200)->withBody($factory->createStream('handled')));
        $request = $factory->createServerRequest('GET', '/users');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('handled', (string) $response->getBody());
    }

    public function testOptionsOnPathWithGetRouteReturns204AllowAndEmptyBody(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');
        $factory = new Psr17Factory();
        $middleware = new OptionsMiddleware($router, $factory);
        $handler = new RecordingHandler($factory->createResponse(404));
        $request = $factory->createServerRequest('OPTIONS', '/users');

        $response = $middleware->process($request, $handler);

        self::assertSame(0, $handler->calls);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaderLine('Allow'));
        self::assertSame('', (string) $response->getBody());
    }

    public function testOptionsMissingPathFallsBackToStandard404Flow(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');
        $factory = new Psr17Factory();
        $middleware = new OptionsMiddleware($router, $factory);
        $handler = new RecordingHandler($factory->createResponse(404)->withBody($factory->createStream('not-found')));
        $request = $factory->createServerRequest('OPTIONS', '/missing');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('not-found', (string) $response->getBody());
    }

    public function testExplicitOptionsRouteHasPriorityOverAutoResponse(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');
        $router->options('/users', 'UserController@options');
        $factory = new Psr17Factory();
        $middleware = new OptionsMiddleware($router, $factory);
        $handler = new RecordingHandler($factory->createResponse(200)->withBody($factory->createStream('explicit-options')));
        $request = $factory->createServerRequest('OPTIONS', '/users');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('explicit-options', (string) $response->getBody());
    }
}

final class RecordingHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ResponseInterface $response,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->calls++;

        return $this->response;
    }
}
