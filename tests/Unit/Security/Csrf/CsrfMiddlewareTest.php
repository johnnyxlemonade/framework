<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Csrf;

use Lemonade\Framework\Security\Csrf\CsrfMiddleware;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddlewareTest extends TestCase
{
    public function testValidMutationRegeneratesToken(): void
    {
        $tokens = $this->tokens();
        $initial = $tokens->token();
        $handler = new CsrfMiddlewareHandler();

        $response = $this->middleware($tokens)->process($this->request('POST', bodyToken: $initial), $handler);

        self::assertSame(1, $handler->calls());
        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame($initial, $response->getHeaderLine('X-CSRF-Token'));
    }

    public function testNextMutationWorksWithTokenReturnedByPreviousResponse(): void
    {
        $tokens = $this->tokens();
        $handler = new CsrfMiddlewareHandler();
        $middleware = $this->middleware($tokens);

        $first = $middleware->process($this->request('POST', bodyToken: $tokens->token()), $handler);
        $second = $middleware->process($this->request('POST', headerToken: $first->getHeaderLine('X-CSRF-Token')), $handler);

        self::assertSame(2, $handler->calls());
        self::assertSame(200, $second->getStatusCode());
    }

    public function testStaleTokenFailsWithoutCallingHandlerAndExposesCurrentToken(): void
    {
        $tokens = $this->tokens();
        $initial = $tokens->token();
        $handler = new CsrfMiddlewareHandler();
        $middleware = $this->middleware($tokens);
        $fresh = $middleware->process($this->request('POST', bodyToken: $initial), $handler)->getHeaderLine('X-CSRF-Token');

        $response = $middleware->process($this->request('POST', bodyToken: $initial), $handler);

        self::assertSame(1, $handler->calls());
        self::assertSame(419, $response->getStatusCode());
        self::assertSame($fresh, $response->getHeaderLine('X-CSRF-Token'));
    }

    public function testBodyFieldRemainsSupported(): void
    {
        $tokens = $this->tokens();
        $handler = new CsrfMiddlewareHandler();

        $response = $this->middleware($tokens)->process($this->request('POST', bodyToken: $tokens->token()), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $handler->calls());
    }

    public function testHeaderRemainsSupported(): void
    {
        $tokens = $this->tokens();
        $handler = new CsrfMiddlewareHandler();

        $response = $this->middleware($tokens)->process($this->request('PATCH', headerToken: $tokens->token()), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $handler->calls());
    }

    private function middleware(CsrfTokenManager $tokens): CsrfMiddleware
    {
        return new CsrfMiddleware($tokens, new Psr17Factory());
    }

    private function tokens(): CsrfTokenManager
    {
        return new CsrfTokenManager(new CsrfMiddlewareSession());
    }

    private function request(string $method, ?string $bodyToken = null, ?string $headerToken = null): ServerRequestInterface
    {
        $request = (new Psr17Factory())->createServerRequest($method, 'https://example.test/admin/api/resource');

        if ($bodyToken !== null) {
            $request = $request->withParsedBody(['LEMONADE_CSRF' => $bodyToken]);
        }

        return $headerToken === null ? $request : $request->withHeader('X-CSRF-Token', $headerToken);
    }
}

final class CsrfMiddlewareHandler implements RequestHandlerInterface
{
    private int $calls = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);
        ++$this->calls;

        return (new Psr17Factory())->createResponse(200);
    }

    public function calls(): int
    {
        return $this->calls;
    }
}

final class CsrfMiddlewareSession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void {}

    public function started(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        unset($deleteOldSession);
    }
}
