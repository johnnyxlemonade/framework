<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareResolverTest extends TestCase
{
    public function testResolveBuildsMiddlewareInstancesFromContainer(): void
    {
        $container = new Container();
        $container->singleton(ResolverMiddlewareOne::class, ResolverMiddlewareOne::class);
        $container->singleton(ResolverMiddlewareTwo::class, ResolverMiddlewareTwo::class);

        $resolver = new MiddlewareResolver($container);
        $resolved = $resolver->resolve([ResolverMiddlewareOne::class, ResolverMiddlewareTwo::class]);

        self::assertCount(2, $resolved);
        self::assertInstanceOf(ResolverMiddlewareOne::class, $resolved[0]);
        self::assertInstanceOf(ResolverMiddlewareTwo::class, $resolved[1]);
    }

    public function testResolveThrowsWhenResolvedClassIsNotMiddleware(): void
    {
        $container = new Container();
        $container->set(\stdClass::class, new \stdClass());

        $resolver = new MiddlewareResolver($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\stdClass::class);
        $resolver->resolve([\stdClass::class]);
    }
}

final class ResolverMiddlewareOne implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class ResolverMiddlewareTwo implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
