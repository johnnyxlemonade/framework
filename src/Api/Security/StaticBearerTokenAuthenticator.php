<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies one configured bearer token in constant time and creates an external API identity.
 */
final readonly class StaticBearerTokenAuthenticator implements ApiAuthenticatorInterface
{
    /**
     * Captures the optional token used for later verification and the permissions assigned on success.
     *
     * @param list<non-empty-string> $scopes
     * @throws \InvalidArgumentException When any configured scope is empty.
     */
    public function __construct(
        private ?string $token,
        private array $scopes = [],
    ) {
        foreach ($scopes as $scope) {
            if ($scope === '') {
                throw new \InvalidArgumentException('Static bearer token scope must not be empty.');
            }
        }
    }

    /**
     * Returns an external static-token identity for an exact bearer match, or null otherwise.
     */
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
            trust: ApiIdentityTrust::External,
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
