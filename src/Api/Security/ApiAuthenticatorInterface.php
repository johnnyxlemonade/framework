<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Psr\Http\Message\ServerRequestInterface;

interface ApiAuthenticatorInterface
{
    public function authenticate(ServerRequestInterface $request): ?ApiIdentity;
}
