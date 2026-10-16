<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Http\Response;

use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRequestResolver;
use Lemonade\Framework\Api\Http\Response\ApiExceptionResponseResolver;
use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Routing\ControllerAction;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApiExceptionResponseResolverTest extends TestCase
{
    public function testUnexpectedApiExceptionReturnsGenericProblemDetails(): void
    {
        $resolver = $this->resolver();

        $response = $resolver->resolve(
            new RuntimeException('Internal exception detail.'),
            new ServerRequest('GET', '/api/broken'),
        );

        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringNotContainsString('Internal exception detail.', (string) $response->getBody());
    }

    public function testNotFoundApiExceptionReturnsProblemDetails(): void
    {
        $resolver = $this->resolver();

        $response = $resolver->resolve(
            NotFoundHttpException::create('Missing API resource.'),
            new ServerRequest('GET', '/api/broken'),
        );

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testNonApiRequestReturnsNull(): void
    {
        $resolver = $this->resolver();

        self::assertNull($resolver->resolve(
            new RuntimeException('Non-API failure.'),
            new ServerRequest('GET', '/web/broken'),
        ));
    }

    private function resolver(): ApiExceptionResponseResolver
    {
        $registry = new ApiEndpointRegistry();
        $registry->get(
            path: '/broken',
            controllerAction: ControllerAction::for('ApiController', 'show'),
            name: 'api.broken',
            summary: 'Broken',
            description: 'Broken endpoint',
            access: ApiAccess::Public,
        );

        return new ApiExceptionResponseResolver(
            new ApiEndpointRequestResolver(
                $registry,
                (new ApiConfigResolver())->resolve(),
            ),
            new ProblemDetailsFactory(new Psr17Factory()),
        );
    }
}
