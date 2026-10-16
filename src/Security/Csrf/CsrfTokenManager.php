<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\SessionNamespace;

/**
 * Maintains independently named tokens inside the default session's CSRF namespace.
 */
final readonly class CsrfTokenManager
{
    private const string NAMESPACE_NAME = 'csrf';

    private SessionNamespace $tokens;

    /**
     * Connects token persistence to the supplied physical session without owning its lifecycle.
     */
    public function __construct(
        SessionInterface $session,
    ) {
        $this->tokens = new SessionNamespace($session, self::NAMESPACE_NAME);
    }

    /**
     * Returns the stable token for a name, creating it when absent or corrupt.
     */
    public function token(string $name = 'default'): string
    {
        $token = $this->tokens->get($name);

        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $this->tokens->set($name, $token);
        }

        return $token;
    }

    /**
     * Compares a submitted value with the token stored for the requested name.
     */
    public function validate(string $token, string $name = 'default'): bool
    {
        $expected = $this->tokens->get($name);

        return is_string($expected)
            && $token !== ''
            && hash_equals($expected, $token);
    }

    /**
     * Replaces and returns only the token associated with the requested name.
     */
    public function regenerate(string $name = 'default'): string
    {
        $token = bin2hex(random_bytes(32));

        $this->tokens->set($name, $token);

        return $token;
    }

    /**
     * Removes one named token while preserving every other CSRF token.
     */
    public function forget(string $name = 'default'): void
    {
        $this->tokens->remove($name);
    }
}
