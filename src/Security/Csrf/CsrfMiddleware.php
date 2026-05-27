<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const FIELD = 'LEMONADE_CSRF';

    /**
     * @var array<int, string>
     */
    private array $methods = [
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];

    public function __construct(
        private readonly CsrfTokenManager $tokens,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array(strtoupper($request->getMethod()), $this->methods, true)) {
            return $handler->handle($request);
        }

        $token = $this->tokenFromRequest($request);

        if (!$this->tokens->validate($token)) {
            $response = $this->responseFactory
                ->createResponse(419)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');

            $response->getBody()->write('419 CSRF token mismatch');

            return $response;
        }

        $this->tokens->regenerate();

        return $handler->handle($request);
    }

    private function tokenFromRequest(ServerRequestInterface $request): string
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody) && isset($parsedBody[self::FIELD])) {
            $value = $parsedBody[self::FIELD];

            if (is_scalar($value) || $value instanceof \Stringable) {
                return (string) $value;
            }
        }

        return $request->getHeaderLine('X-CSRF-Token');
    }
}
