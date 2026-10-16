<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

use Lemonade\Framework\Http\HttpStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces the default CSRF token on unsafe requests and exposes it on every response.
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private const array SAFE_METHODS = [
        'GET',
        'HEAD',
        'OPTIONS',
    ];

    /**
     * Connects request validation to session-backed tokens and PSR-17 error responses.
     */
    public function __construct(
        private CsrfTokenManager $tokens,
        private Psr17Factory $responseFactory,
    ) {
    }

    /**
     * Validates unsafe requests before dispatch and attaches the current token to the response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $handler->handle($request)->withHeader(CsrfTokenNames::HEADER, $this->tokens->token());
        }

        $token = $this->tokenFromRequest($request);

        if (!$this->tokens->validate($token)) {
            $body = '419 CSRF token mismatch';

            return $this->responseFactory
                ->createResponse(HttpStatus::CSRF_TOKEN_MISMATCH->value)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader(CsrfTokenNames::HEADER, $this->tokens->token())
                ->withBody($this->responseFactory->createStream($body));
        }

        return $handler->handle($request)->withHeader(CsrfTokenNames::HEADER, $this->tokens->token());
    }

    /**
     * Reads a submitted token from the canonical form field before falling back to the request header.
     */
    private function tokenFromRequest(ServerRequestInterface $request): string
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody) && isset($parsedBody[CsrfTokenNames::FORM_FIELD])) {
            $value = $parsedBody[CsrfTokenNames::FORM_FIELD];

            if (is_scalar($value) || $value instanceof \Stringable) {
                return (string) $value;
            }
        }

        return $request->getHeaderLine(CsrfTokenNames::HEADER);
    }
}
