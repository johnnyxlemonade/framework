<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Http\Middleware\MiddlewarePipeline;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewarePipelineTest extends TestCase
{
    public function testEmptyStackCallsFallbackHandler(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/');
        $fallback = new PipelineFallbackHandler('fallback');

        $pipeline = MiddlewarePipeline::create([], $fallback);
        $response = $pipeline->handle($request);

        self::assertSame(1, $fallback->calls);
        self::assertSame('fallback', (string) $response->getBody());
    }

    public function testMiddlewareOrderRequestMutationResponseMutationAndShortCircuitBehavior(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/');
        $fallback = new PipelineFallbackHandler('fallback');
        $recorder = new PipelineRecorder($request);

        $m1 = new PipelineMiddlewareOne($recorder);
        $m2 = new PipelineMiddlewareTwo($recorder);

        $pipeline = MiddlewarePipeline::create([10 => $m1, 20 => $m2], $fallback);
        $response = $pipeline->handle($request);

        self::assertSame(
            ['m1_same_request', 'm1_before', 'm2_request_mutated', 'm2_before', 'm2_after', 'm1_after'],
            $recorder->events,
        );
        self::assertSame('1', $response->getHeaderLine('X-M1'));
        self::assertSame('1', $response->getHeaderLine('X-M2'));
        self::assertSame(1, $fallback->calls);

        $short = new PipelineShortCircuitMiddleware();
        $pipelineShort = MiddlewarePipeline::create([99 => $short], $fallback);
        $shortResponse = $pipelineShort->handle($request);
        self::assertSame('short', (string) $shortResponse->getBody());
        self::assertSame(1, $fallback->calls);
    }
}

final class PipelineRecorder
{
    /** @var list<string> */
    public array $events = [];

    public function __construct(public readonly ServerRequestInterface $originalRequest) {}
}

final class PipelineMiddlewareOne implements MiddlewareInterface
{
    public function __construct(private readonly PipelineRecorder $recorder) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->recorder->events[] = $request === $this->recorder->originalRequest ? 'm1_same_request' : 'm1_changed_request';
        $this->recorder->events[] = 'm1_before';
        $response = $handler->handle($request->withAttribute('from_m1', 'yes'));
        $this->recorder->events[] = 'm1_after';

        return $response->withHeader('X-M1', '1');
    }
}

final class PipelineMiddlewareTwo implements MiddlewareInterface
{
    public function __construct(private readonly PipelineRecorder $recorder) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->recorder->events[] = $request !== $this->recorder->originalRequest ? 'm2_request_mutated' : 'm2_request_original';
        $this->recorder->events[] = 'm2_before';
        $response = $handler->handle($request);
        $this->recorder->events[] = 'm2_after';

        return $response->withHeader('X-M2', '1');
    }
}

final class PipelineShortCircuitMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        unset($request, $handler);
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream('short'));
    }
}

final class PipelineFallbackHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public function __construct(private readonly string $body) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);
        $this->calls++;
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream($this->body));
    }
}
