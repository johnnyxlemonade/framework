<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Middleware\CorsMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddlewareTest extends TestCase
{
    public function testDisabledCorsDoesNotChangeResponse(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware(['cors' => ['enabled' => false]]);
        $handler = new CorsRecordingHandler($factory->createResponse(200)->withBody($factory->createStream('ok')));
        $request = $factory->createServerRequest('GET', '/users')->withHeader('Origin', 'https://app.test');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testRequestWithoutOriginPassesThroughUnchanged(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://app.test'],
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200));
        $request = $factory->createServerRequest('GET', '/users');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testValidPreflightReturns204WithExpectedHeaders(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://app.test'],
                'allowed_methods' => ['GET', 'POST'],
                'allowed_headers' => ['Content-Type', 'Authorization'],
                'allow_credentials' => true,
                'max_age' => 600,
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(404));
        $request = $factory->createServerRequest('OPTIONS', '/users')
            ->withHeader('Origin', 'https://app.test')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'Authorization');

        $response = $middleware->process($request, $handler);

        self::assertSame(0, $handler->calls);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame('https://app.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('GET, POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type, Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertSame('600', $response->getHeaderLine('Access-Control-Max-Age'));
        self::assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    public function testInvalidPreflightOriginReturns403(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://allowed.test'],
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200));
        $request = $factory->createServerRequest('OPTIONS', '/users')
            ->withHeader('Origin', 'https://blocked.test')
            ->withHeader('Access-Control-Request-Method', 'GET');

        $response = $middleware->process($request, $handler);

        self::assertSame(0, $handler->calls);
        self::assertSame(403, $response->getStatusCode());
    }

    public function testValidCorsGetAddsAllowOriginHeader(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://app.test'],
                'exposed_headers' => ['X-Trace-Id'],
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200));
        $request = $factory->createServerRequest('GET', '/users')->withHeader('Origin', 'https://app.test');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame('https://app.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('X-Trace-Id', $response->getHeaderLine('Access-Control-Expose-Headers'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    public function testRequestFromDisallowedOriginDoesNotAddCorsHeaders(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://app.test'],
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200));
        $request = $factory->createServerRequest('GET', '/users')->withHeader('Origin', 'https://blocked.test');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testCredentialsWithWildcardDoesNotReturnAsteriskAndUsesConcreteOrigin(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://app.test'],
                'allow_credentials' => true,
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200));
        $request = $factory->createServerRequest('GET', '/users')->withHeader('Origin', 'https://app.test');

        $response = $middleware->process($request, $handler);

        self::assertSame('https://app.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertNotSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testCredentialsWithWildcardOriginConfigurationThrowsException(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['*'],
                'allow_credentials' => true,
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200));
        $request = $factory->createServerRequest('GET', '/users')->withHeader('Origin', 'https://app.test');

        $this->expectException(\InvalidArgumentException::class);
        $middleware->process($request, $handler);
    }

    public function testVaryOriginIsNotDuplicatedCaseInsensitive(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://app.test'],
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200)->withHeader('Vary', 'origin'));
        $request = $factory->createServerRequest('GET', '/users')->withHeader('Origin', 'https://app.test');

        $response = $middleware->process($request, $handler);

        self::assertSame('origin', $response->getHeaderLine('Vary'));
    }

    public function testOptionsWithoutOriginIsNotTreatedAsCorsPreflight(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['https://app.test'],
                'allowed_methods' => ['GET'],
            ],
        ]);
        $handler = new CorsRecordingHandler($factory->createResponse(200)->withBody($factory->createStream('passthrough')));
        $request = $factory->createServerRequest('OPTIONS', '/users')
            ->withHeader('Access-Control-Request-Method', 'GET');

        $response = $middleware->process($request, $handler);

        self::assertSame(1, $handler->calls);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('passthrough', (string) $response->getBody());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function middleware(array $overrides): CorsMiddleware
    {
        $factory = new Psr17Factory();
        $cors = isset($overrides['cors']) && is_array($overrides['cors'])
            ? $overrides['cors']
            : [];

        return new CorsMiddleware(
            new CorsConfig(
                enabled: $this->toBool($cors['enabled'] ?? false),
                allowedOrigins: $this->stringList($cors['allowed_origins'] ?? []),
                allowedMethods: $this->stringList($cors['allowed_methods'] ?? []),
                allowedHeaders: $this->stringList($cors['allowed_headers'] ?? []),
                exposedHeaders: $this->stringList($cors['exposed_headers'] ?? []),
                allowCredentials: $this->toBool($cors['allow_credentials'] ?? false),
                maxAge: $this->nullableInt($cors['max_age'] ?? null),
            ),
            $factory,
        );
    }

    /**
     * @return list<non-empty-string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $item = trim((string) $value);
            if ($item === '' || in_array($item, $normalized, true)) {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) === true;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}

final class CorsRecordingHandler implements RequestHandlerInterface
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
