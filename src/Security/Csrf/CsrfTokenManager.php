<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Csrf;

use Lemonade\Framework\Session\Contract\SessionInterface;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_tokens';

    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function token(string $name = 'default'): string
    {
        $tokens = $this->tokens();

        if (!isset($tokens[$name])) {
            $tokens[$name] = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $tokens);
        }

        return $tokens[$name];
    }

    public function validate(string $token, string $name = 'default'): bool
    {
        $expected = $this->tokens()[$name] ?? null;

        return is_string($expected)
            && $token !== ''
            && hash_equals($expected, $token);
    }

    public function regenerate(string $name = 'default'): string
    {
        $tokens = $this->tokens();
        $tokens[$name] = bin2hex(random_bytes(32));

        $this->session->set(self::SESSION_KEY, $tokens);

        return $tokens[$name];
    }

    public function forget(string $name = 'default'): void
    {
        $tokens = $this->tokens();

        unset($tokens[$name]);

        $this->session->set(self::SESSION_KEY, $tokens);
    }

    /**
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $tokens = $this->session->get(self::SESSION_KEY, []);

        if (!is_array($tokens)) {
            return [];
        }

        $normalized = [];

        foreach ($tokens as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
