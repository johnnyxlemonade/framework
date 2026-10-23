<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Provides fail-closed authentication when no API credential mechanism is configured.
 */
final readonly class NullApiAuthenticator implements ApiAuthenticatorInterface
{
    /**
     * Always returns null and never derives identity from request metadata.
     */
    public function authenticate(ServerRequestInterface $request): ?ApiIdentity
    {
        unset($request);

        return null;
    }
}
