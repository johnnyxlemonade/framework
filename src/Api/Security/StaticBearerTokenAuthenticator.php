<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Psr\Http\Message\ServerRequestInterface;

final class StaticBearerTokenAuthenticator implements ApiAuthenticatorInterface
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private readonly ?string $token,
        private readonly array $scopes = [],
    ) {}

    public function authenticate(ServerRequestInterface $request): ?ApiIdentity
    {
        if ($this->token === null || trim($this->token) === '') {
            return null;
        }

        $providedToken = $this->extractBearerToken($request);

        if ($providedToken === null) {
            return null;
        }

        if (!hash_equals($this->token, $providedToken)) {
            return null;
        }

        return new ApiIdentity(
            id: 'static-token',
            type: 'static_token',
            scopes: $this->scopes,
            name: 'Static API token',
        );
    }

    private function extractBearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
            return null;
        }

        $token = trim($matches[1]);

        return $token !== '' ? $token : null;
    }
}
