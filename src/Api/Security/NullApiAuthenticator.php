<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Psr\Http\Message\ServerRequestInterface;

final class NullApiAuthenticator implements ApiAuthenticatorInterface
{
    public function authenticate(ServerRequestInterface $request): ?ApiIdentity
    {
        unset($request);

        return null;
    }
}
