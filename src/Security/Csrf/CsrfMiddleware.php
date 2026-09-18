<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private const SAFE_METHODS = [
        'GET',
        'HEAD',
        'OPTIONS',
    ];

    public function __construct(
        private readonly CsrfTokenManager $tokens,
        private readonly Psr17Factory $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $handler->handle($request)->withHeader(CsrfTokenNames::HEADER, $this->tokens->token());
        }

        $token = $this->tokenFromRequest($request);

        if (!$this->tokens->validate($token)) {
            $body = '419 CSRF token mismatch';

            return $this->responseFactory
                ->createResponse(419)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader(CsrfTokenNames::HEADER, $this->tokens->token())
                ->withBody($this->responseFactory->createStream($body));
        }

        return $handler->handle($request)->withHeader(CsrfTokenNames::HEADER, $this->tokens->token());
    }

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
