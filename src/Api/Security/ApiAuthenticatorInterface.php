<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies request credentials and returns only identity claims established by that verification.
 *
 * Implementations own credential transport and may assign internal trust only from authenticated evidence.
 */
interface ApiAuthenticatorInterface
{
    /**
     * Returns the verified identity for the request, or null when authentication cannot be established.
     */
    public function authenticate(ServerRequestInterface $request): ?ApiIdentity;
}
